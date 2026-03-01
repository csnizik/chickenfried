#!/usr/bin/env python3
"""
preprocess_lamb_era.py — Phase 0b: Preprocess LAMB CSVs for pre-2010 data.

MUST run AFTER build_pedi_registry.py (Phase 0a) because this script
loads the PEDI registry and relationship map for validation.

ID CONSTRUCTION (pre-2010 format):
  Live lambs (LAMB etag present):
    ID10 = "19" + YR(2) + LBRD(2) + zfill(LAMB,4)

  Disposed lambs (LAMB empty, typically BRWT=0.0):
    ID10 = "D" + "19" + YR(2) + zfill(DAM,4) + cdno_last_char

  Sire ID10:
    If zfill(SIRE,4) == "0000" → US code: US + SBRD(2) + YRSIRE(2) + zfill(DAM,4)
    Else → "19" + YRSIRE(2) + SBRD(2) + zfill(SIRE,4)

  Dam ID10:
    If zfill(DAM,4) == "0000" → UD code: UD + DBRD(2) + YRDAM(2) + zfill(SIRE,4)
    Else → "19" + YRDAM(2) + DBRD(2) + zfill(DAM,4)

OUTPUTS:
  preprocessed/LAMB{YEAR}.preprocessed.csv — Original columns + ID10, SIREID10, DAMID10, UID
  preprocessed/LAMB{YEAR}.report.txt       — QA report with validation results

USAGE:
  python3 preprocess_lamb_era.py path/to/LAMB1950.csv --registry path/to/registry/
  python3 preprocess_lamb_era.py path/to/lamb-csv/ --registry path/to/registry/
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

DEFAULT_CENTURY = "19"

PLACEHOLDERS = {
    "", ".", "NO.DATA", "NOT.FOUND", "LAMBNOTFOUND", "NOLAMBFOUND",
    "NO.BANDS", "NO.BAND", "NOTFOUND", "NIL", "NA",
}
ALNUM_RE = re.compile(r"[^A-Z0-9]")
YEAR_RE = re.compile(r"(\d{4})")


# ---------------------------------------------------------------------------
# Utility functions (shared with existing preprocessing scripts)
# ---------------------------------------------------------------------------

def clean_val(v):
    if v is None:
        return ""
    s = str(v).strip().upper()
    if s in PLACEHOLDERS:
        return ""
    return s


def normalize_etag(v):
    s = clean_val(v)
    if not s:
        return ""
    return ALNUM_RE.sub("", s)


def zfill4(v):
    """Zero-pad a value to 4 digits. Returns '0000' if empty."""
    s = normalize_etag(v)
    if not s:
        return "0000"
    return s[-4:].zfill(4)


def zfill2(v):
    """Zero-pad a value to 2 digits. Returns '00' if empty."""
    s = clean_val(v)
    if not s:
        return "00"
    s = ALNUM_RE.sub("", s)
    if not s:
        return "00"
    return s[-2:].zfill(2)


def extract_year_from_filename(path):
    m = YEAR_RE.search(os.path.basename(path))
    return m.group(1) if m else None


def cdno_final_char(cdno, rownum):
    """Extract last alphanumeric char from CDNO, fallback to last digit of rownum."""
    cd = clean_val(cdno)
    cd = re.sub(r"[^A-Z0-9]", "", cd)
    if cd:
        return cd[-1]
    return str(rownum % 10)


# ---------------------------------------------------------------------------
# ID construction
# ---------------------------------------------------------------------------

def build_live_id10(century, yr, lbrd, lamb):
    """Build ID10 for a live lamb: century(2) + YR(2) + LBRD(2) + LAMB(4) = 10 chars."""
    return f"{century}{zfill2(yr)}{zfill2(lbrd)}{zfill4(lamb)}"


def build_disposed_id10(century, yr, dam, cdno, rownum):
    """Build ID10 for a disposed lamb: D + century(2) + YR(2) + DAM(4) + CDNO_char(1) = 10 chars."""
    return f"D{century}{zfill2(yr)}{zfill4(dam)}{cdno_final_char(cdno, rownum)}"


def build_parent_id10(century, yr_parent, breed_parent, etag_parent):
    """Build ID10 for a known parent: century(2) + YR(2) + BRD(2) + ETAG(4) = 10 chars."""
    return f"{century}{zfill2(yr_parent)}{zfill2(breed_parent)}{zfill4(etag_parent)}"


def build_unknown_sire_code(sbrd, yrsire, dam_etag):
    """Build synthetic unknown sire code: US + SBRD(2) + YRSIRE(2) + DAM_ETAG(4) = 10 chars."""
    return f"US{zfill2(sbrd)}{zfill2(yrsire)}{zfill4(dam_etag)}"


def build_unknown_dam_code(dbrd, yrdam, sire_etag):
    """Build synthetic unknown dam code: UD + DBRD(2) + YRDAM(2) + SIRE_ETAG(4) = 10 chars."""
    return f"UD{zfill2(dbrd)}{zfill2(yrdam)}{zfill4(sire_etag)}"


def is_unknown_etag(etag):
    """Check if a 4-digit etag is all zeros (unknown parent)."""
    return zfill4(etag) == "0000"


def is_disposed_row(row):
    """Detect disposed lamb: empty LAMB etag is the primary signal."""
    lamb_etag = normalize_etag(row.get("LAMB", ""))
    if not lamb_etag:
        return True
    return False


# ---------------------------------------------------------------------------
# Registry loading
# ---------------------------------------------------------------------------

def load_pedi_registry(registry_dir):
    """Load pedi_registry.csv into a lookup dict keyed by ID10."""
    path = os.path.join(registry_dir, "pedi_registry.csv")
    registry = {}
    if not os.path.exists(path):
        print(f"  WARNING: No registry file at {path}")
        return registry
    with open(path, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            registry[row["id10"]] = row
    print(f"  Loaded PEDI registry: {len(registry)} animals")
    return registry


def load_pedi_relationships(registry_dir):
    """Load pedi_relationships.csv into a lookup dict keyed by lamb_id10."""
    path = os.path.join(registry_dir, "pedi_relationships.csv")
    rels = {}
    if not os.path.exists(path):
        print(f"  WARNING: No relationships file at {path}")
        return rels
    with open(path, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            rels[row["lamb_id10"]] = {
                "sire_id10": row["sire_id10"],
                "dam_id10": row["dam_id10"],
            }
    print(f"  Loaded PEDI relationships: {len(rels)} records")
    return rels


# ---------------------------------------------------------------------------
# File processing
# ---------------------------------------------------------------------------

def process_file(src_path, out_dir, century, pedi_registry, pedi_rels):
    """Process a single LAMB CSV file.

    Adds: ID10, SIREID10, DAMID10, UID (if missing)
    Validates constructed IDs against PEDI registry.
    """
    basename = os.path.basename(src_path)
    year_str = extract_year_from_filename(basename) or "0000"
    out_path = os.path.join(out_dir, basename.replace(".csv", ".preprocessed.csv"))
    report_path = os.path.join(out_dir, basename.replace(".csv", ".report.txt"))

    total = 0
    written = 0
    live_count = 0
    disposed_count = 0
    unknown_sires = 0
    unknown_dams = 0
    id10_length_errors = []
    pedi_matches = 0
    pedi_mismatches = []
    pedi_not_found = 0
    duplicates = []
    seen_id10 = {}

    with open(src_path, newline="", encoding="utf-8", errors="replace") as inf:
        reader = csv.DictReader(inf)
        fieldnames = list(reader.fieldnames or [])

        # Add new columns
        extra_cols = []
        for col in ["ID10", "SIREID10", "DAMID10"]:
            if col not in fieldnames:
                extra_cols.append(col)
        # Ensure UID exists
        has_uid = "UID" in fieldnames
        if not has_uid:
            extra_cols.append("UID")
        out_fields = fieldnames + extra_cols

        os.makedirs(out_dir, exist_ok=True)
        with open(out_path, "w", newline="", encoding="utf-8") as outf:
            writer = csv.DictWriter(outf, fieldnames=out_fields, extrasaction="ignore")
            writer.writeheader()

            for rownum, row in enumerate(reader, start=1):
                total += 1

                yr = clean_val(row.get("YR", ""))
                lamb_etag = row.get("LAMB", "")
                lbrd = row.get("LBRD", "")
                sire_etag = row.get("SIRE", "")
                sbrd = row.get("SBRD", "")
                yrsire = row.get("YRSIRE", "")
                dam_etag = row.get("DAM", "")
                dbrd = row.get("DBRD", "")
                yrdam = row.get("YRDAM", "")
                cdno = row.get("CDNO", "")

                # --- Build Lamb ID10 ---
                if is_disposed_row(row):
                    id10 = build_disposed_id10(century, yr, dam_etag, cdno, rownum)
                    disposed_count += 1
                else:
                    id10 = build_live_id10(century, yr, lbrd, lamb_etag)
                    live_count += 1

                # --- Build Sire ID10 ---
                if is_unknown_etag(sire_etag):
                    sire_id10 = build_unknown_sire_code(sbrd, yrsire, dam_etag)
                    unknown_sires += 1
                else:
                    sire_id10 = build_parent_id10(century, yrsire, sbrd, sire_etag)

                # --- Build Dam ID10 ---
                if is_unknown_etag(dam_etag):
                    dam_id10 = build_unknown_dam_code(dbrd, yrdam, sire_etag)
                    unknown_dams += 1
                else:
                    dam_id10 = build_parent_id10(century, yrdam, dbrd, dam_etag)

                # --- Validate ID10 length ---
                if len(id10) != 10:
                    id10_length_errors.append((rownum, id10, len(id10)))
                    continue  # Skip row

                if len(sire_id10) != 10:
                    id10_length_errors.append((rownum, f"SIRE:{sire_id10}", len(sire_id10)))
                if len(dam_id10) != 10:
                    id10_length_errors.append((rownum, f"DAM:{dam_id10}", len(dam_id10)))

                # --- PEDI override: use PEDI as authoritative for parent IDs ---
                if not is_disposed_row(row) and pedi_rels:
                    if id10 in pedi_rels:
                        pedi_rel = pedi_rels[id10]
                        if pedi_rel["sire_id10"] == sire_id10 and pedi_rel["dam_id10"] == dam_id10:
                            pedi_matches += 1
                        else:
                            pedi_mismatches.append({
                                "row": rownum,
                                "id10": id10,
                                "lamb_sire": sire_id10,
                                "pedi_sire": pedi_rel["sire_id10"],
                                "lamb_dam": dam_id10,
                                "pedi_dam": pedi_rel["dam_id10"],
                            })
                            # PEDI is authoritative — override LAMB-constructed values
                            sire_id10 = pedi_rel["sire_id10"]
                            dam_id10 = pedi_rel["dam_id10"]
                    else:
                        pedi_not_found += 1

                # --- Resolve disposed ID collisions ---
                if id10 in seen_id10:
                    if is_disposed_row(row):
                        # Increment last character to resolve collision
                        original_id10 = id10
                        attempt = 0
                        while id10 in seen_id10 and attempt < 10:
                            attempt += 1
                            # Replace last char with incremented digit
                            base = id10[:9]
                            last = id10[9]
                            if last.isdigit():
                                new_last = str((int(last) + 1) % 10)
                            else:
                                new_last = str(attempt)
                            id10 = base + new_last
                        if id10 in seen_id10:
                            # Exhausted attempts — use rownum suffix
                            id10 = f"D{century}{zfill2(yr)}{str(rownum).zfill(4)[-4:]}X"
                        duplicates.append((original_id10, seen_id10[original_id10], rownum, id10))
                    else:
                        duplicates.append((id10, seen_id10[id10], rownum, id10))
                seen_id10[id10] = rownum

                # --- Generate UID if missing ---
                if not has_uid or not clean_val(row.get("UID", "")):
                    # Format: YYLAROWNUM (matches dbf2csv pattern)
                    row["UID"] = f"{zfill2(yr)}LA{str(rownum).zfill(5)}"

                # --- Write enriched row ---
                row["ID10"] = id10
                row["SIREID10"] = sire_id10
                row["DAMID10"] = dam_id10
                writer.writerow(row)
                written += 1

    # --- QA Report ---
    with open(report_path, "w", encoding="utf-8") as rep:
        rep.write(f"=== LAMB Preprocessing Report: {basename} ===\n")
        rep.write(f"Year: {year_str}\n")
        rep.write(f"Century prefix: {century}\n\n")

        rep.write(f"Total rows: {total}\n")
        rep.write(f"Rows written: {written}\n")
        rep.write(f"  Live lambs: {live_count}\n")
        rep.write(f"  Disposed lambs: {disposed_count}\n")
        rep.write(f"  Unknown sires (→ US code): {unknown_sires}\n")
        rep.write(f"  Unknown dams (→ UD code): {unknown_dams}\n\n")

        rep.write(f"ID10 length errors (rows skipped): {len(id10_length_errors)}\n")
        if id10_length_errors:
            for e in id10_length_errors[:100]:
                rep.write(f"  row {e[0]}: '{e[1]}' (len={e[2]})\n")

        rep.write(f"\nDuplicate ID10s (resolved): {len(duplicates)}\n")
        if duplicates:
            for d in duplicates[:100]:
                rep.write(f"  {d[0]} first_row:{d[1]} dup_row:{d[2]} → resolved to:{d[3]}\n")

        rep.write(f"\n=== PEDI Cross-Validation (PEDI overrides LAMB on mismatch) ===\n")
        rep.write(f"Matched PEDI (lamb+sire+dam agree): {pedi_matches}\n")
        rep.write(f"Overridden by PEDI (sire or dam differs): {len(pedi_mismatches)}\n")
        rep.write(f"Not in PEDI: {pedi_not_found}\n")
        if pedi_mismatches:
            rep.write("\nMismatches:\n")
            for m in pedi_mismatches[:100]:
                rep.write(f"  row {m['row']} ID10={m['id10']}\n")
                rep.write(f"    LAMB sire={m['lamb_sire']}  PEDI sire={m['pedi_sire']}\n")
                rep.write(f"    LAMB dam ={m['lamb_dam']}  PEDI dam ={m['pedi_dam']}\n")

    print(f"  Preprocessed: {out_path} ({written}/{total} rows)")
    print(f"  Live: {live_count}, Disposed: {disposed_count}, "
          f"US: {unknown_sires}, UD: {unknown_dams}")
    print(f"  PEDI validation: {pedi_matches} match, "
          f"{len(pedi_mismatches)} overridden, {pedi_not_found} not in PEDI")

    return {
        "file": basename, "total": total, "written": written,
        "live": live_count, "disposed": disposed_count,
        "unknown_sires": unknown_sires, "unknown_dams": unknown_dams,
        "id10_errors": len(id10_length_errors),
        "duplicates": len(duplicates),
        "pedi_matches": pedi_matches,
        "pedi_mismatches": len(pedi_mismatches),
        "pedi_not_found": pedi_not_found,
    }


# ---------------------------------------------------------------------------
# Main driver
# ---------------------------------------------------------------------------

def find_lamb_files(path):
    """Find LAMB CSV files from a path (file or directory)."""
    p = Path(path)
    if p.is_file() and p.suffix.lower() == ".csv":
        return [p]
    if p.is_dir():
        return sorted(p.glob("LAMB*.csv"))
    return []


def main():
    parser = argparse.ArgumentParser(
        description="Preprocess LAMB CSVs for pre-2010 data: add ID10, SIREID10, DAMID10",
    )
    parser.add_argument(
        "input",
        help="Path to a LAMB CSV file or directory containing LAMB*.csv files",
    )
    parser.add_argument(
        "--registry",
        help="Path to PEDI registry directory (containing pedi_registry.csv and pedi_relationships.csv)",
    )
    parser.add_argument(
        "--century", default=DEFAULT_CENTURY,
        help=f"Century prefix for 2-digit years (default: {DEFAULT_CENTURY})",
    )
    parser.add_argument(
        "--outdir",
        help="Output directory (default: <input_dir>/preprocessed)",
    )
    args = parser.parse_args()

    files = find_lamb_files(args.input)
    if not files:
        print(f"ERROR: No LAMB CSV files found at: {args.input}")
        sys.exit(1)

    out_dir = args.outdir or str(files[0].parent / "preprocessed")

    # Load PEDI registry if available
    pedi_registry = {}
    pedi_rels = {}
    if args.registry:
        print(f"Loading PEDI registry from: {args.registry}")
        pedi_registry = load_pedi_registry(args.registry)
        pedi_rels = load_pedi_relationships(args.registry)
    else:
        print("WARNING: No --registry specified. Skipping PEDI cross-validation.")

    print(f"\n{'='*60}")
    print(f"LAMB Preprocessor (pre-2010 era)")
    print(f"{'='*60}")
    print(f"Input files: {len(files)}")
    for f in files:
        print(f"  {f.name}")
    print(f"Century prefix: {args.century}")
    print(f"Output: {out_dir}")
    print(f"{'='*60}\n")

    all_stats = []

    for f in files:
        print(f"Processing: {f.name}")
        stats = process_file(str(f), out_dir, args.century, pedi_registry, pedi_rels)
        all_stats.append(stats)
        print()

    # Summary
    print(f"{'='*60}")
    print(f"SUMMARY")
    print(f"{'='*60}")
    for s in all_stats:
        print(f"  {s['file']}: {s['written']}/{s['total']} rows "
              f"({s['live']} live, {s['disposed']} disposed)")
        print(f"    US: {s['unknown_sires']}, UD: {s['unknown_dams']}, "
              f"ID errors: {s['id10_errors']}, Dups: {s['duplicates']}")
        print(f"    PEDI: {s['pedi_matches']} match, "
              f"{s['pedi_mismatches']} overridden, {s['pedi_not_found']} not found")
    print(f"{'='*60}\n")


if __name__ == "__main__":
    main()
