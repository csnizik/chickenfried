#!/usr/bin/env python3
"""
build_pedi_registry.py — Phase 0a: Build Master ID Registry from PEDI files.

Must run BEFORE any LAMB or WEANMAS preprocessing because those scripts
depend on the registry and relationship map produced here.

FORMAT AUTO-DETECTION:
  "8digit" (pre-2010): Columns UID, LAMB, SIRE, DAM with 8-digit composite IDs.
    8-digit format: YY(2) + BB(2) + EEEE(4)
    ID10 = century_prefix + 8-digit value (e.g., "19" + "50113217" = "1950113217")

  "separated" (2010+): Columns include SIREID, DAMID as already-10-char values.
    Uses existing normalization logic from preprocess_pedi.py.

UNKNOWN PARENT DETECTION (8digit format):
  If etag portion (last 4 digits) = "0000", the parent is unknown/placeholder.
  Unknown sires → US + SIRE_BRD(2) + SIRE_YR(2) + DAM_ETAG(4) = 10 chars
  Unknown dams  → UD + DAM_BRD(2)  + DAM_YR(2)  + SIRE_ETAG(4) = 10 chars

OUTPUTS:
  registry/pedi_registry.csv       — All unique ID10s with components
  registry/pedi_relationships.csv  — lamb_id10 → sire_id10, dam_id10
  registry/registry_report.txt     — Summary statistics
  preprocessed/PEDI{YEAR}.preprocessed.csv — Original + ID10, SIREID10, DAMID10
  preprocessed/PEDI{YEAR}.report.txt       — Per-file QA report

USAGE:
  python3 build_pedi_registry.py path/to/pedi/              # All PEDI*.csv in dir
  python3 build_pedi_registry.py path/to/PEDI1950.csv       # Single file
  python3 build_pedi_registry.py path/to/pedi/ --century 19  # Explicit century
"""

import csv
import os
import sys
import re
import argparse
from pathlib import Path
from collections import defaultdict

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

# Default century prefix for 2-digit year expansion.
# For 1950-1999 data, this is always "19".
# For 2000+ data in 8-digit format (if encountered), pass --century 20.
DEFAULT_CENTURY = "19"

# Placeholder patterns (reused from existing preprocessing scripts)
PLACEHOLDERS = {
    "", ".", "NO.DATA", "NOT.FOUND", "LAMBNOTFOUND", "NOLAMBFOUND",
    "NO.BANDS", "NO.BAND", "NOTFOUND", "NIL", "NA",
}
RE_ALL_ZERO = re.compile(r"^0+$")
RE_LETTER_ZERO = re.compile(r"^[A-Za-z]0{3,4}$")


# ---------------------------------------------------------------------------
# Format detection
# ---------------------------------------------------------------------------

def detect_pedi_format(fieldnames):
    """Detect PEDI format from column headers.

    Returns:
        "8digit"    — Pre-2010: columns are UID, LAMB, SIRE, DAM (8-digit composites)
        "separated" — 2010+: columns include SIREID or DAMID (already 10-char)
        None        — Unknown format
    """
    names = set(f.strip().upper() for f in fieldnames)
    if "SIREID" in names or "DAMID" in names:
        return "separated"
    if "LAMB" in names and "SIRE" in names and "DAM" in names:
        return "8digit"
    return None


# ---------------------------------------------------------------------------
# 8-digit composite ID parsing
# ---------------------------------------------------------------------------

def parse_8digit(val):
    """Parse an 8-digit composite ID into components.

    Format: YY(2) + BB(2) + EEEE(4)
    Returns dict with year, breed, etag, raw — or None if invalid.
    """
    s = str(val).strip()
    if len(s) != 8:
        return None
    if not s[:2].isdigit():
        return None
    return {
        "year": s[0:2],
        "breed": s[2:4],
        "etag": s[4:8],
        "raw": s,
    }


def is_unknown_parent_8digit(parsed):
    """An 8-digit ID is an unknown/placeholder if etag portion is all zeros."""
    if parsed is None:
        return True
    return parsed["etag"] == "0000"


def make_id10(parsed, century):
    """Convert parsed 8-digit to 10-character ID10."""
    if parsed is None:
        return None
    return f"{century}{parsed['raw']}"


def make_unknown_sire_code(sire_parsed, dam_parsed):
    """Build synthetic 10-char unknown sire code.

    Format: US + SIRE_BRD(2) + SIRE_YR(2) + DAM_ETAG(4) = 10 chars.
    Groups unknown sires per litter: same sire breed+year and same dam
    produce the same US code.
    """
    s_brd = sire_parsed["breed"] if sire_parsed else "00"
    s_yr = sire_parsed["year"] if sire_parsed else "00"
    d_etag = "0000"
    if dam_parsed and dam_parsed["etag"] != "0000":
        d_etag = dam_parsed["etag"]
    code = f"US{s_brd}{s_yr}{d_etag}"
    if len(code) != 10:
        raise ValueError(f"US code length error: '{code}' ({len(code)} chars)")
    return code


def make_unknown_dam_code(dam_parsed, sire_parsed):
    """Build synthetic 10-char unknown dam code.

    Format: UD + DAM_BRD(2) + DAM_YR(2) + SIRE_ETAG(4) = 10 chars.
    """
    d_brd = dam_parsed["breed"] if dam_parsed else "00"
    d_yr = dam_parsed["year"] if dam_parsed else "00"
    s_etag = "0000"
    if sire_parsed and sire_parsed["etag"] != "0000":
        s_etag = sire_parsed["etag"]
    code = f"UD{d_brd}{d_yr}{s_etag}"
    if len(code) != 10:
        raise ValueError(f"UD code length error: '{code}' ({len(code)} chars)")
    return code


# ---------------------------------------------------------------------------
# Separated format (2010+) helpers
# ---------------------------------------------------------------------------

def is_placeholder_id(val):
    """Check if a SIREID/DAMID value is a placeholder."""
    if val is None:
        return True
    s = str(val).strip()
    if s == "" or s.upper() in PLACEHOLDERS:
        return True
    if RE_ALL_ZERO.fullmatch(s):
        return True
    if RE_LETTER_ZERO.fullmatch(s):
        return True
    return False


def make_unknown_sire_code_separated(sbrd, yr, dam):
    """Build US code from separated columns (2010+ format).

    Formula: US + SBRD(2) + YR(last2) + DAM(last4) = 10 chars.
    """
    sbrd_part = (sbrd or "").strip().upper()[:2].zfill(2)
    yr_part = (yr or "").strip()[-2:].zfill(2) if yr else "00"
    dam_part = (dam or "").strip()[-4:].zfill(4) if dam else "0000"
    code = f"US{sbrd_part}{yr_part}{dam_part}"
    return (code + "0" * 10)[:10]


def make_unknown_dam_code_separated(dbrd, yr, sire):
    """Build UD code from separated columns (2010+ format).

    Formula: UD + DBRD(2) + YR(last2) + SIRE(last4) = 10 chars.
    """
    dbrd_part = (dbrd or "").strip().upper()[:2].zfill(2)
    yr_part = (yr or "").strip()[-2:].zfill(2) if yr else "00"
    sire_part = (sire or "").strip()[-4:].zfill(4) if sire else "0000"
    code = f"UD{dbrd_part}{yr_part}{sire_part}"
    return (code + "0" * 10)[:10]


# ---------------------------------------------------------------------------
# Registry data structures
# ---------------------------------------------------------------------------

class PediRegistry:
    """Accumulates all unique animal IDs and relationships across PEDI files."""

    def __init__(self):
        # id10 → {birth_year, breed, etag, id_type, sources}
        self.animals = {}
        # List of relationship dicts
        self.relationships = []
        # Counters for reporting
        self.stats = defaultdict(int)

    def add_animal(self, id10, birth_year, breed, etag, id_type, source):
        """Register a unique animal. Deduplicates by ID10."""
        if id10 in self.animals:
            self.animals[id10]["sources"].add(source)
            return
        self.animals[id10] = {
            "birth_year": birth_year,
            "breed": breed,
            "etag": etag,
            "id_type": id_type,
            "sources": {source},
        }
        self.stats[f"animals_{id_type}"] += 1

    def add_relationship(self, lamb_id10, sire_id10, dam_id10, source, row):
        """Record a pedigree relationship."""
        self.relationships.append({
            "lamb_id10": lamb_id10,
            "sire_id10": sire_id10,
            "dam_id10": dam_id10,
            "source_file": source,
            "source_row": row,
        })

    def write_registry_csv(self, path):
        """Write pedi_registry.csv — all unique animals."""
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w", newline="", encoding="utf-8") as f:
            w = csv.writer(f)
            w.writerow(["id10", "birth_year", "breed", "etag", "id_type", "sources"])
            for id10 in sorted(self.animals.keys()):
                a = self.animals[id10]
                w.writerow([
                    id10,
                    a["birth_year"],
                    a["breed"],
                    a["etag"],
                    a["id_type"],
                    "|".join(sorted(a["sources"])),
                ])
        print(f"  Registry: {path} ({len(self.animals)} unique animals)")

    def write_relationships_csv(self, path):
        """Write pedi_relationships.csv — all lamb→sire,dam triples."""
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w", newline="", encoding="utf-8") as f:
            w = csv.writer(f)
            w.writerow(["lamb_id10", "sire_id10", "dam_id10", "source_file", "source_row"])
            for r in self.relationships:
                w.writerow([
                    r["lamb_id10"],
                    r["sire_id10"],
                    r["dam_id10"],
                    r["source_file"],
                    r["source_row"],
                ])
        print(f"  Relationships: {path} ({len(self.relationships)} records)")

    def write_report(self, path):
        """Write registry_report.txt — summary statistics."""
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w", encoding="utf-8") as f:
            f.write("=== PEDI Registry Report ===\n\n")
            f.write(f"Total unique animals: {len(self.animals)}\n")
            for key in sorted(self.stats.keys()):
                f.write(f"  {key}: {self.stats[key]}\n")
            f.write(f"\nTotal relationships: {len(self.relationships)}\n")

            # ID10 length violations
            bad = [k for k in self.animals if len(k) != 10]
            if bad:
                f.write(f"\n⚠ ID10 length violations: {len(bad)}\n")
                for b in bad[:50]:
                    f.write(f"  '{b}' (len={len(b)})\n")

            # Duplicate relationships (same lamb in multiple PEDI rows)
            lamb_counts = defaultdict(int)
            for r in self.relationships:
                lamb_counts[r["lamb_id10"]] += 1
            dups = {k: v for k, v in lamb_counts.items() if v > 1}
            if dups:
                f.write(f"\n⚠ Lambs appearing in multiple PEDI rows: {len(dups)}\n")
                for k in sorted(dups.keys())[:50]:
                    f.write(f"  {k}: {dups[k]} rows\n")

            # Unknown parent summary
            us_codes = [k for k in self.animals if k.startswith("US")]
            ud_codes = [k for k in self.animals if k.startswith("UD")]
            if us_codes:
                f.write(f"\nUnique unknown sire codes (US): {len(us_codes)}\n")
                for c in sorted(us_codes)[:30]:
                    f.write(f"  {c}\n")
            if ud_codes:
                f.write(f"\nUnique unknown dam codes (UD): {len(ud_codes)}\n")
                for c in sorted(ud_codes)[:30]:
                    f.write(f"  {c}\n")

        print(f"  Report: {path}")


# ---------------------------------------------------------------------------
# File processing — 8-digit format
# ---------------------------------------------------------------------------

def process_8digit_file(src_path, out_dir, registry, century):
    """Process a pre-2010 PEDI CSV with 8-digit composite IDs.

    Adds columns: ID10, SIREID10, DAMID10 and extracted components.
    Registers all animals and relationships in the registry.
    """
    basename = os.path.basename(src_path)
    out_path = os.path.join(out_dir, basename.replace(".csv", ".preprocessed.csv"))
    report_path = os.path.join(out_dir, basename.replace(".csv", ".report.txt"))

    total = 0
    written = 0
    unknown_sires = 0
    unknown_dams = 0
    parse_errors = []

    with open(src_path, newline="", encoding="utf-8", errors="replace") as inf:
        reader = csv.DictReader(inf)
        fieldnames = list(reader.fieldnames or [])
        out_fields = fieldnames + [
            "ID10", "SIREID10", "DAMID10",
            "LAMB_YR", "LAMB_BRD", "LAMB_ETAG",
            "SIRE_YR", "SIRE_BRD", "SIRE_ETAG",
            "DAM_YR", "DAM_BRD", "DAM_ETAG",
        ]

        os.makedirs(out_dir, exist_ok=True)
        with open(out_path, "w", newline="", encoding="utf-8") as outf:
            writer = csv.DictWriter(outf, fieldnames=out_fields, extrasaction="ignore")
            writer.writeheader()

            for rownum, row in enumerate(reader, start=1):
                total += 1

                lamb_raw = row.get("LAMB", "").strip()
                sire_raw = row.get("SIRE", "").strip()
                dam_raw = row.get("DAM", "").strip()

                lamb_p = parse_8digit(lamb_raw)
                sire_p = parse_8digit(sire_raw)
                dam_p = parse_8digit(dam_raw)

                if lamb_p is None:
                    parse_errors.append((rownum, "LAMB", lamb_raw))
                    continue

                # --- Construct lamb ID10 ---
                lamb_id10 = make_id10(lamb_p, century)

                # --- Construct sire ID10 ---
                if sire_p is None:
                    parse_errors.append((rownum, "SIRE", sire_raw))
                    sire_id10 = f"US0000{lamb_p['etag']}"
                elif is_unknown_parent_8digit(sire_p):
                    sire_id10 = make_unknown_sire_code(sire_p, dam_p)
                    unknown_sires += 1
                else:
                    sire_id10 = make_id10(sire_p, century)

                # --- Construct dam ID10 ---
                if dam_p is None:
                    parse_errors.append((rownum, "DAM", dam_raw))
                    dam_id10 = f"UD0000{lamb_p['etag']}"
                elif is_unknown_parent_8digit(dam_p):
                    dam_id10 = make_unknown_dam_code(dam_p, sire_p)
                    unknown_dams += 1
                else:
                    dam_id10 = make_id10(dam_p, century)

                # --- Register all three animals ---
                registry.add_animal(
                    lamb_id10, lamb_p["year"], lamb_p["breed"],
                    lamb_p["etag"], "lamb", basename,
                )

                if sire_id10.startswith("US"):
                    registry.add_animal(
                        sire_id10,
                        sire_p["year"] if sire_p else "00",
                        sire_p["breed"] if sire_p else "00",
                        "0000", "unknown_sire", basename,
                    )
                else:
                    registry.add_animal(
                        sire_id10,
                        sire_p["year"] if sire_p else "00",
                        sire_p["breed"] if sire_p else "00",
                        sire_p["etag"] if sire_p else "0000",
                        "sire", basename,
                    )

                if dam_id10.startswith("UD"):
                    registry.add_animal(
                        dam_id10,
                        dam_p["year"] if dam_p else "00",
                        dam_p["breed"] if dam_p else "00",
                        "0000", "unknown_dam", basename,
                    )
                else:
                    registry.add_animal(
                        dam_id10,
                        dam_p["year"] if dam_p else "00",
                        dam_p["breed"] if dam_p else "00",
                        dam_p["etag"] if dam_p else "0000",
                        "dam", basename,
                    )

                # --- Record relationship ---
                registry.add_relationship(
                    lamb_id10, sire_id10, dam_id10, basename, rownum,
                )

                # --- Write enriched row ---
                row["ID10"] = lamb_id10
                row["SIREID10"] = sire_id10
                row["DAMID10"] = dam_id10
                row["LAMB_YR"] = lamb_p["year"]
                row["LAMB_BRD"] = lamb_p["breed"]
                row["LAMB_ETAG"] = lamb_p["etag"]
                row["SIRE_YR"] = sire_p["year"] if sire_p else ""
                row["SIRE_BRD"] = sire_p["breed"] if sire_p else ""
                row["SIRE_ETAG"] = sire_p["etag"] if sire_p else ""
                row["DAM_YR"] = dam_p["year"] if dam_p else ""
                row["DAM_BRD"] = dam_p["breed"] if dam_p else ""
                row["DAM_ETAG"] = dam_p["etag"] if dam_p else ""
                writer.writerow(row)
                written += 1

    # --- Per-file QA report ---
    with open(report_path, "w", encoding="utf-8") as rep:
        rep.write(f"=== PEDI Preprocessing Report: {basename} ===\n")
        rep.write(f"Format detected: 8digit\n")
        rep.write(f"Century prefix: {century}\n\n")
        rep.write(f"Total rows: {total}\n")
        rep.write(f"Rows written: {written}\n")
        rep.write(f"Unknown sires (→ US code): {unknown_sires}\n")
        rep.write(f"Unknown dams (→ UD code): {unknown_dams}\n")
        rep.write(f"Parse errors (skipped): {len(parse_errors)}\n")
        if parse_errors:
            rep.write("\nParse errors (row, field, raw_value):\n")
            for e in parse_errors[:200]:
                rep.write(f"  row {e[0]}: {e[1]} = '{e[2]}'\n")

    print(f"  Preprocessed: {out_path} ({written}/{total} rows)")
    return {
        "total": total, "written": written,
        "unknown_sires": unknown_sires, "unknown_dams": unknown_dams,
        "parse_errors": len(parse_errors),
    }


# ---------------------------------------------------------------------------
# File processing — separated format (2010+)
# ---------------------------------------------------------------------------

def process_separated_file(src_path, out_dir, registry, century):
    """Process a 2010+ PEDI CSV with separated SIREID/DAMID columns.

    Reuses unknown-parent logic from original preprocess_pedi.py.
    Adds ID10, SIREID10, DAMID10 columns (normalized/synthetic as needed).
    """
    basename = os.path.basename(src_path)
    out_path = os.path.join(out_dir, basename.replace(".csv", ".preprocessed.csv"))
    report_path = os.path.join(out_dir, basename.replace(".csv", ".report.txt"))

    total = 0
    written = 0
    unknown_sires = 0
    unknown_dams = 0

    with open(src_path, newline="", encoding="utf-8", errors="replace") as inf:
        reader = csv.DictReader(inf)
        fieldnames = list(reader.fieldnames or [])
        extra = [c for c in ["ID10", "SIREID10", "DAMID10"] if c not in fieldnames]
        out_fields = fieldnames + extra

        os.makedirs(out_dir, exist_ok=True)
        with open(out_path, "w", newline="", encoding="utf-8") as outf:
            writer = csv.DictWriter(outf, fieldnames=out_fields, extrasaction="ignore")
            writer.writeheader()

            for rownum, row in enumerate(reader, start=1):
                total += 1

                lamb_id = (row.get("LAMBID") or row.get("ID10") or "").strip()
                sire_id = (row.get("SIREID") or "").strip()
                dam_id = (row.get("DAMID") or "").strip()

                if is_placeholder_id(sire_id):
                    sbrd = row.get("SBRD", "")
                    yr = row.get("YR", "")
                    dam = row.get("DAM", "")
                    sire_id = make_unknown_sire_code_separated(sbrd, yr, dam)
                    unknown_sires += 1

                if is_placeholder_id(dam_id):
                    dbrd = row.get("DBRD", "")
                    yr = row.get("YR", "")
                    sire = row.get("SIRE", "")
                    dam_id = make_unknown_dam_code_separated(dbrd, yr, sire)
                    unknown_dams += 1

                # Register animals
                if lamb_id and len(lamb_id) == 10:
                    registry.add_animal(
                        lamb_id, row.get("YR", ""),
                        row.get("LBRD", row.get("BRD", "")),
                        row.get("LAMB", row.get("ETAG", "")),
                        "lamb", basename,
                    )
                if sire_id and len(sire_id) == 10:
                    id_type = "unknown_sire" if sire_id.startswith("US") else "sire"
                    registry.add_animal(
                        sire_id, row.get("YRSIRE", ""),
                        row.get("SBRD", ""), row.get("SIRE", ""),
                        id_type, basename,
                    )
                if dam_id and len(dam_id) == 10:
                    id_type = "unknown_dam" if dam_id.startswith("UD") else "dam"
                    registry.add_animal(
                        dam_id, row.get("YRDAM", ""),
                        row.get("DBRD", ""), row.get("DAM", ""),
                        id_type, basename,
                    )

                if lamb_id and sire_id and dam_id:
                    registry.add_relationship(
                        lamb_id, sire_id, dam_id, basename, rownum,
                    )

                row["ID10"] = lamb_id
                row["SIREID10"] = sire_id
                row["DAMID10"] = dam_id
                writer.writerow(row)
                written += 1

    with open(report_path, "w", encoding="utf-8") as rep:
        rep.write(f"=== PEDI Preprocessing Report: {basename} ===\n")
        rep.write(f"Format detected: separated\n\n")
        rep.write(f"Total rows: {total}\n")
        rep.write(f"Rows written: {written}\n")
        rep.write(f"Unknown sires (→ US code): {unknown_sires}\n")
        rep.write(f"Unknown dams (→ UD code): {unknown_dams}\n")

    print(f"  Preprocessed: {out_path} ({written}/{total} rows)")
    return {
        "total": total, "written": written,
        "unknown_sires": unknown_sires, "unknown_dams": unknown_dams,
        "parse_errors": 0,
    }


# ---------------------------------------------------------------------------
# Main driver
# ---------------------------------------------------------------------------

def find_pedi_files(path):
    """Find PEDI CSV files from a path (file or directory)."""
    p = Path(path)
    if p.is_file() and p.suffix.lower() == ".csv":
        return [p]
    if p.is_dir():
        return sorted(p.glob("PEDI*.csv"))
    return []


def main():
    parser = argparse.ArgumentParser(
        description="Build PEDI master registry and preprocess PEDI CSVs",
    )
    parser.add_argument(
        "input",
        help="Path to a PEDI CSV file or directory containing PEDI*.csv files",
    )
    parser.add_argument(
        "--century", default=DEFAULT_CENTURY,
        help=f"Century prefix for 2-digit years (default: {DEFAULT_CENTURY})",
    )
    parser.add_argument(
        "--outdir",
        help="Output base directory (default: same as input directory)",
    )
    args = parser.parse_args()

    files = find_pedi_files(args.input)
    if not files:
        print(f"ERROR: No PEDI CSV files found at: {args.input}")
        sys.exit(1)

    base_dir = Path(args.outdir) if args.outdir else files[0].parent
    preproc_dir = base_dir / "preprocessed"
    registry_dir = base_dir / "registry"

    print(f"\n{'='*60}")
    print(f"PEDI Registry Builder")
    print(f"{'='*60}")
    print(f"Input files: {len(files)}")
    for f in files:
        print(f"  {f.name}")
    print(f"Century prefix: {args.century}")
    print(f"Preprocessed output: {preproc_dir}")
    print(f"Registry output: {registry_dir}")
    print(f"{'='*60}\n")

    registry = PediRegistry()
    file_stats = []

    for f in files:
        print(f"Processing: {f.name}")

        with open(f, newline="", encoding="utf-8", errors="replace") as fh:
            reader = csv.DictReader(fh)
            if not reader.fieldnames:
                print(f"  SKIP: No headers in {f.name}")
                continue
            fmt = detect_pedi_format(reader.fieldnames)

        if fmt is None:
            print(f"  SKIP: Unknown format in {f.name}")
            continue

        print(f"  Format: {fmt}")

        if fmt == "8digit":
            stats = process_8digit_file(str(f), str(preproc_dir), registry, args.century)
        else:
            stats = process_separated_file(str(f), str(preproc_dir), registry, args.century)

        stats["file"] = f.name
        stats["format"] = fmt
        file_stats.append(stats)
        print()

    # Write cumulative registry
    print(f"Writing registry files...")
    registry.write_registry_csv(str(registry_dir / "pedi_registry.csv"))
    registry.write_relationships_csv(str(registry_dir / "pedi_relationships.csv"))
    registry.write_report(str(registry_dir / "registry_report.txt"))

    # Summary
    print(f"\n{'='*60}")
    print(f"SUMMARY")
    print(f"{'='*60}")
    print(f"Files processed: {len(file_stats)}")
    print(f"Total unique animals in registry: {len(registry.animals)}")
    print(f"Total relationships recorded: {len(registry.relationships)}")
    for s in file_stats:
        print(f"  {s['file']}: {s['written']}/{s['total']} rows, "
              f"{s['unknown_sires']} US, {s['unknown_dams']} UD, "
              f"{s['parse_errors']} errors")
    print(f"{'='*60}\n")

    bad_ids = [k for k in registry.animals if len(k) != 10]
    if bad_ids:
        print(f"⚠ WARNING: {len(bad_ids)} animals have ID10 != 10 chars!")
        for b in bad_ids[:10]:
            print(f"  '{b}' (len={len(b)})")
    else:
        print("✓ All ID10 values are exactly 10 characters.")


if __name__ == "__main__":
    main()
