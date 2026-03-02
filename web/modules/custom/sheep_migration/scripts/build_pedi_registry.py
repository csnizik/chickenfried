#!/usr/bin/env python3
"""
build_pedi_registry.py — Phase 0a: Build Master ID Registry from PEDI files.

Must run BEFORE any LAMB or WEANMAS preprocessing because those scripts
depend on the registry and relationship map produced here.

ID10 NORMALIZATION:
  All IDs across all eras are normalized to YYYYBBEEEE (10 chars):
    YYYY = 4-digit birth year
    BB   = 2-digit breed code
    EEEE = 4-digit ear tag

  Source ID serial-to-year conversion:
    First digit 2-9: pad leading 0, read 3-digit serial, YYYY = 1900 + serial
    First digit 1:   read 3-digit serial (100-106), YYYY = 1900 + serial
    First digit 0:   leading "00" = year 2000, YYYY = 2000
    First 4 digits form YYYY (2007+): already normalized

  Dot-notation fix (1970-1980):
    Some IDs use "YY.BBEEE" format (8 chars with dot) where the etag's
    leading zero was dropped. Fix: YY + BB + 0 + EEE → 8-digit standard.
    Breeds affected: 10, 40, 60, 70, 80, 90.

FORMAT AUTO-DETECTION:
  "8digit" (1950-1997): Columns UID, LAMB, SIRE, DAM
  "separated" (2000+):  Columns include LAMBID, SIREID, DAMID

UNKNOWN PARENT DETECTION:
  etag = "0000" indicates unknown/placeholder parent.
  breed = "00"/"0" with real etag indicates purchased animal (no US/UD code).
  Unknown sires → US + BRD(2) + YR_2DIGIT(2) + OTHER_PARENT_ETAG(4) = 10 chars
  Unknown dams  → UD + BRD(2) + YR_2DIGIT(2) + OTHER_PARENT_ETAG(4) = 10 chars

HISTORICAL STUBS (HX prefix):
  Animals born before --cutoff-year (default: 1950) get HX + 8-digit sequential
  IDs. Permanently minimal stubs. Original ID10 preserved for traceability.

PURCHASED ANIMALS:
  breed "00" or "0" with real etag → is_purchased = 1 in registry.
  Migration sets field_s_is_purchased = TRUE, field_s_purchased_date = Jan 1 of
  the year the animal first appears.

OUTPUTS:
  registry/pedi_registry.csv           — All unique ID10s with components
  registry/pedi_relationships.csv      — lamb_id10 → sire_id10, dam_id10
  registry/hx_id_map.csv               — original_id10 → hx_id10 mapping
  registry/registry_report.txt         — Summary statistics
  preprocessed/PEDI{YEAR}.preprocessed.csv — Enriched with ID10, SIREID10, DAMID10
  preprocessed/PEDI{YEAR}.report.txt       — Per-file QA report

USAGE:
  python3 build_pedi_registry.py path/to/pedi/                    # All PEDI*.csv
  python3 build_pedi_registry.py path/to/PEDI1950.csv             # Single file
  python3 build_pedi_registry.py path/to/pedi/ --cutoff-year 1950 # With cutoff
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

DEFAULT_CUTOFF_YEAR = 1950

PLACEHOLDERS = {
    "", ".", "NO.DATA", "NOT.FOUND", "LAMBNOTFOUND", "NOLAMBFOUND",
    "NO.BANDS", "NO.BAND", "NOTFOUND", "NIL", "NA",
}
RE_ALL_ZERO = re.compile(r"^0+$")
RE_LETTER_ZERO = re.compile(r"^[A-Za-z]0{3,4}$")


# ---------------------------------------------------------------------------
# Universal ID10 normalization
# ---------------------------------------------------------------------------

def normalize_to_id10(raw):
    """Normalize any-era raw numeric ID to YYYYBBEEEE (10 chars).

    Serial-to-year rules:
      First digit 2-9 → pad leading 0, 3-digit serial, YYYY = 1900 + serial
      First digit 1   → 3-digit serial (100-106), YYYY = 1900 + serial
      First digit 0   → leading "00" means year 2000
      Already 10 digits starting with 19xx/20xx → already normalized

    Dot-notation fix (1970-1980 data):
      "YY.BBEEE" (8 chars with dot) → "YYBB0EEE" (8 digits).
      The dot sits between 2-digit year and 5-char breed+etag.
      The etag lost its leading zero; we restore it.

    Returns dict: {id10, year_serial, yyyy, breed, etag, raw} or None.
    """
    s = str(raw).strip()

    # Fix dot-notation IDs found in 1970-1980 data.
    # Format: "YY.BBEEE" (8 chars with dot) → "YYBB0EEE" (8 digits).
    # The dot sits between 2-digit year and 5-char breed+etag.
    # The etag lost its leading zero; we restore it.
    if "." in s:
        parts = s.split(".")
        if len(parts) == 2 and len(parts[0]) == 2 and len(parts[1]) == 5:
            yy, rest = parts
            # rest = BB (2 chars) + EEE (3 chars, missing leading 0)
            s = f"{yy}{rest[:2]}0{rest[2:]}"
        else:
            return None  # unexpected dot format

    if not s or not s.isdigit():
        return None

    # Determine serial prefix and remainder
    first = s[0]

    if first in "23456789":
        # 8-digit ID: serial is 2 digits (50-99). Pad to 3.
        # Format: SS BB EEEE (8 chars total)
        if len(s) != 8:
            return None
        serial = int(f"0{s[0:2]}")  # e.g., "50" → 050 → 50... wait
        # Actually: pad a 0 before it, read first 3 digits = "050"
        padded = f"0{s}"  # "050130882" (9 chars)
        serial = int(padded[0:3])  # 050 = 50
        yyyy = 1900 + serial       # 1950
        breed = s[2:4]
        etag = s[4:8]

    elif first == "1":
        # 9-digit ID: serial is 3 digits (100-106).
        # Format: SSS BB EEEE (9 chars total)
        if len(s) != 9:
            return None
        serial = int(s[0:3])        # 100-106
        yyyy = 1900 + serial        # 2000-2006
        breed = s[3:5]
        etag = s[5:9]

    elif first == "0":
        # 8-digit ID for year 2000: "00" prefix + BB + EEEE
        # Format: 00 BB EEEE (8 chars)
        if len(s) != 8:
            return None
        # Leading "00" = year 2000
        yyyy = 2000
        serial = 100
        breed = s[2:4]
        etag = s[4:8]

    else:
        return None

    # Already-normalized 10-digit IDs starting with 2007+
    # won't reach here because first digit would be "2" and len != 8.
    # But we need to handle them. Let's check: a 10-digit ID starting
    # with "2007" has first digit "2" and len 10 — the "2-9" branch
    # rejects it because len != 8. We need an explicit 10-digit check.

    id10 = f"{yyyy:04d}{breed}{etag}"
    if len(id10) != 10:
        return None

    return {
        "id10": id10,
        "yyyy": yyyy,
        "year_serial": str(serial),
        "breed": breed,
        "etag": etag,
        "raw": s,
    }


def normalize_to_id10_any(raw):
    """Normalize any ID to ID10, including already-10-char IDs from 2007+.

    Tries normalize_to_id10 first (for 8/9-digit serial IDs).
    Falls back to checking if already a valid 10-char YYYYBBEEEE.

    Also handles:
      - Space-padded IDs like '200800 179' → '2008000179'
      - 1999 10-digit IDs that don't fit the serial pattern
    """
    s = str(raw).strip()
    if not s:
        return None

    # Replace internal spaces with zeros (e.g., '200800 179' → '2008000179')
    # These are space-padded IDs where the etag leading zeros were replaced
    # with spaces for right-justification in the original data entry.
    if " " in s:
        s = s.replace(" ", "0")

    # Try serial-based normalization first (8 or 9 digit IDs)
    result = normalize_to_id10(s)
    if result:
        return result

    # Check if already a 10-digit ID (1950+ files may reference parents
    # with fully-formed YYYYBBEEEE IDs, including 1999)
    if len(s) == 10 and s.isdigit():
        yyyy = int(s[0:4])
        if 1950 <= yyyy <= 2099:
            return {
                "id10": s,
                "yyyy": yyyy,
                "year_serial": s[0:4],
                "breed": s[4:6],
                "etag": s[6:10],
                "raw": s,
            }

    return None


def extract_components(id10):
    """Extract YYYY, breed, etag from a normalized ID10 string."""
    if not id10 or len(id10) != 10:
        return None
    return {
        "yyyy": int(id10[0:4]),
        "breed": id10[4:6],
        "etag": id10[6:10],
    }


# ---------------------------------------------------------------------------
# Format detection
# ---------------------------------------------------------------------------

def detect_pedi_format(fieldnames):
    """Detect PEDI format from column headers.

    Returns "8digit" (1950-1997), "separated" (2000+), or None.
    """
    names = set(f.strip().upper() for f in fieldnames)
    if "SIREID" in names or "DAMID" in names or "LAMBID" in names:
        return "separated"
    if "LAMB" in names and "SIRE" in names and "DAM" in names:
        return "8digit"
    return None


# ---------------------------------------------------------------------------
# Unknown parent detection and synthetic code generation
# ---------------------------------------------------------------------------

def is_unknown_parent(parsed):
    """An ID represents an unknown parent if etag is all zeros."""
    if parsed is None:
        return True
    return parsed["etag"] == "0000"


def is_placeholder_id(val):
    """Check if a raw SIREID/DAMID value is a placeholder (for separated format)."""
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


def make_unknown_sire_code(sire_breed, sire_yr_2digit, other_parent_etag):
    """Build synthetic 10-char unknown sire code.

    Format: US + BRD(2) + YR(2) + OTHER_ETAG(4) = 10 chars.
    """
    brd = str(sire_breed or "00").strip()[:2].zfill(2)
    yr = str(sire_yr_2digit or "00").strip()[-2:].zfill(2)
    etag = str(other_parent_etag or "0000").strip()[-4:].zfill(4)
    code = f"US{brd}{yr}{etag}"
    assert len(code) == 10, f"US code length error: '{code}' ({len(code)} chars)"
    return code


def make_unknown_dam_code(dam_breed, dam_yr_2digit, other_parent_etag):
    """Build synthetic 10-char unknown dam code.

    Format: UD + BRD(2) + YR(2) + OTHER_ETAG(4) = 10 chars.
    """
    brd = str(dam_breed or "00").strip()[:2].zfill(2)
    yr = str(dam_yr_2digit or "00").strip()[-2:].zfill(2)
    etag = str(other_parent_etag or "0000").strip()[-4:].zfill(4)
    code = f"UD{brd}{yr}{etag}"
    assert len(code) == 10, f"UD code length error: '{code}' ({len(code)} chars)"
    return code


def yr_2digit_from_yyyy(yyyy):
    """Get last 2 digits of a year as zero-padded string."""
    return f"{int(yyyy) % 100:02d}"


def normalize_us_code(code):
    """Normalize unknown-sire codes that differ only in prefix encoding.

    In PEDI1967-1968, some breed-90 crossbred animals were listed twice:
    once with sire US00XXXXXX and again with US18XXXXXX. Both refer to the
    same unknown sire. We canonicalize US18 → US00 so they merge.

    The pattern: US + 2-char prefix + 2-digit year + 4-digit etag.
    US18 was an alternate encoding; US00 is the canonical form.
    """
    if not code.startswith("US18"):
        return code
    # US18XXXXXX → US00XXXXXX  (positions 2-3 change from "18" to "00")
    return "US00" + code[4:]


# ---------------------------------------------------------------------------
# Registry data structures
# ---------------------------------------------------------------------------

class PediRegistry:
    """Accumulates all unique animal IDs and relationships across PEDI files."""

    def __init__(self):
        self.animals = {}
        self.relationships = []
        self.seen_lambs = {}       # lamb_id10 → first relationship dict
        self.duplicate_lambs = []  # list of {lamb, first, duplicate, category}
        self.stats = defaultdict(int)

    def add_animal(self, id10, birth_year, breed, etag, id_type, source):
        if id10 in self.animals:
            self.animals[id10]["sources"].add(source)
            return
        breed_s = str(breed).strip()
        etag_s = str(etag).strip()
        is_purchased = breed_s in ("00", "0") and etag_s != "0000" and etag_s != ""
        self.animals[id10] = {
            "birth_year": birth_year,
            "breed": breed,
            "etag": etag,
            "id_type": id_type,
            "is_purchased": is_purchased,
            "sources": {source},
        }
        self.stats[f"animals_{id_type}"] += 1
        if is_purchased:
            self.stats["animals_purchased"] += 1

    def add_relationship(self, lamb_id10, sire_id10, dam_id10, source, row):
        """Add a lamb→sire/dam relationship. First occurrence wins.

        Duplicates are logged and categorized:
          IDENTICAL:   same sire + dam (true duplicate row)
          US_VARIANT:  sire differs only in US00/US18 prefix (same animal)
          CONFLICT:    genuinely different parents (needs researcher review)
        """
        new_rel = {
            "lamb_id10": lamb_id10,
            "sire_id10": sire_id10,
            "dam_id10": dam_id10,
            "source_file": source,
            "source_row": row,
        }

        if lamb_id10 in self.seen_lambs:
            first = self.seen_lambs[lamb_id10]

            # Categorize the duplicate
            if first["sire_id10"] == sire_id10 and first["dam_id10"] == dam_id10:
                category = "IDENTICAL"
            elif (normalize_us_code(first["sire_id10"]) == normalize_us_code(sire_id10)
                  and first["dam_id10"] == dam_id10):
                category = "US_VARIANT"
            else:
                category = "CONFLICT"

            self.duplicate_lambs.append({
                "lamb_id10": lamb_id10,
                "category": category,
                "first": dict(first),
                "duplicate": dict(new_rel),
            })
            self.stats[f"dup_{category.lower()}"] += 1
            return  # Skip — first occurrence wins

        self.seen_lambs[lamb_id10] = new_rel
        self.relationships.append(new_rel)

    def apply_hx_remapping(self, cutoff_year):
        """Identify pre-cutoff animals and assign HX IDs.

        Birth year is derived from the first 4 chars of the normalized ID10.
        Only remaps real animals, NOT synthetic US/UD codes.
        Returns: dict mapping original_id10 → hx_id10
        """
        hx_map = {}
        hx_counter = 0

        pre_cutoff_ids = []
        for id10, info in sorted(self.animals.items()):
            if id10.startswith("US") or id10.startswith("UD") or id10.startswith("HX"):
                continue

            # Birth year from normalized ID10
            try:
                full_year = int(id10[0:4])
            except (ValueError, IndexError):
                continue

            if full_year < cutoff_year:
                pre_cutoff_ids.append(id10)

        pre_cutoff_ids.sort()

        for original_id10 in pre_cutoff_ids:
            hx_counter += 1
            hx_id10 = f"HX{hx_counter:08d}"
            assert len(hx_id10) == 10, f"HX ID length error: '{hx_id10}'"
            hx_map[original_id10] = hx_id10

        for original_id10, hx_id10 in hx_map.items():
            info = self.animals.pop(original_id10)
            info["original_id10"] = original_id10
            info["is_historical_stub"] = True
            self.animals[hx_id10] = info
            self.stats["animals_historical_stub"] += 1
            orig_type = info["id_type"]
            if f"animals_{orig_type}" in self.stats:
                self.stats[f"animals_{orig_type}"] -= 1

        for id10, info in self.animals.items():
            if "is_historical_stub" not in info:
                info["is_historical_stub"] = False
                info["original_id10"] = id10

        for rel in self.relationships:
            if rel["sire_id10"] in hx_map:
                rel["sire_id10"] = hx_map[rel["sire_id10"]]
            if rel["dam_id10"] in hx_map:
                rel["dam_id10"] = hx_map[rel["dam_id10"]]
            if rel["lamb_id10"] in hx_map:
                rel["lamb_id10"] = hx_map[rel["lamb_id10"]]

        return hx_map

    def write_registry_csv(self, path):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w", newline="", encoding="utf-8") as f:
            w = csv.writer(f)
            w.writerow([
                "id10", "birth_year", "breed", "etag", "id_type",
                "is_historical_stub", "is_purchased", "original_id10", "sources",
            ])
            for id10 in sorted(self.animals.keys()):
                a = self.animals[id10]
                w.writerow([
                    id10,
                    a["birth_year"],
                    a["breed"],
                    a["etag"],
                    a["id_type"],
                    "1" if a.get("is_historical_stub", False) else "0",
                    "1" if a.get("is_purchased", False) else "0",
                    a.get("original_id10", id10),
                    "|".join(sorted(a["sources"])),
                ])
        print(f"  Registry: {path} ({len(self.animals)} unique animals)")

    def write_relationships_csv(self, path):
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

    def write_hx_map_csv(self, path, hx_map):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w", newline="", encoding="utf-8") as f:
            w = csv.writer(f)
            w.writerow(["original_id10", "hx_id10", "birth_year", "breed", "etag"])
            for orig in sorted(hx_map.keys()):
                hx_id = hx_map[orig]
                info = self.animals.get(hx_id, {})
                w.writerow([
                    orig,
                    hx_id,
                    info.get("birth_year", ""),
                    info.get("breed", ""),
                    info.get("etag", ""),
                ])
        print(f"  HX map: {path} ({len(hx_map)} pre-cutoff animals remapped)")

    def write_report(self, path, cutoff_year=None, hx_map=None):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w", encoding="utf-8") as f:
            f.write("=== PEDI Registry Report ===\n\n")
            f.write(f"Total unique animals: {len(self.animals)}\n")
            if cutoff_year:
                f.write(f"Cutoff year: {cutoff_year}\n")
            for key in sorted(self.stats.keys()):
                f.write(f"  {key}: {self.stats[key]}\n")
            f.write(f"\nTotal relationships: {len(self.relationships)}\n")

            if hx_map:
                f.write(f"\n=== Historical Stubs (HX) ===\n")
                f.write(f"Pre-cutoff animals remapped to HX: {len(hx_map)}\n\n")
                f.write(f"{'Original ID10':<16} {'HX ID10':<14} {'Birth Yr':<10} {'Breed':<8} {'Etag':<8}\n")
                f.write(f"{'-'*16} {'-'*14} {'-'*10} {'-'*8} {'-'*8}\n")
                for orig in sorted(hx_map.keys()):
                    hx_id = hx_map[orig]
                    info = self.animals.get(hx_id, {})
                    f.write(f"{orig:<16} {hx_id:<14} {info.get('birth_year', ''):<10} "
                            f"{info.get('breed', ''):<8} {info.get('etag', ''):<8}\n")

            bad = [k for k in self.animals if len(k) != 10]
            if bad:
                f.write(f"\n⚠ ID10 length violations: {len(bad)}\n")
                for b in bad[:50]:
                    f.write(f"  '{b}' (len={len(b)})\n")
            else:
                f.write(f"\n✓ All ID10 values are exactly 10 characters.\n")

            lamb_counts = defaultdict(int)
            for r in self.relationships:
                lamb_counts[r["lamb_id10"]] += 1
            dups = {k: v for k, v in lamb_counts.items() if v > 1}
            if dups:
                f.write(f"\n⚠ Lambs STILL appearing in multiple PEDI rows "
                        f"(after dedup): {len(dups)}\n")
                for k in sorted(dups.keys())[:50]:
                    f.write(f"  {k}: {dups[k]} rows\n")
            else:
                f.write(f"\n✓ No duplicate lambs in relationships (all deduped).\n")

            # Dedup summary
            if self.duplicate_lambs:
                ident = len([d for d in self.duplicate_lambs if d["category"] == "IDENTICAL"])
                usvar = len([d for d in self.duplicate_lambs if d["category"] == "US_VARIANT"])
                confl = len([d for d in self.duplicate_lambs if d["category"] == "CONFLICT"])
                f.write(f"\nDuplicate lambs resolved: {len(self.duplicate_lambs)}\n")
                f.write(f"  IDENTICAL (dropped): {ident}\n")
                f.write(f"  US_VARIANT (US18→US00, dropped): {usvar}\n")
                f.write(f"  CONFLICT (first row kept): {confl}\n")
                f.write(f"  See duplicate_conflicts.md for full details.\n")

            us_codes = [k for k in self.animals if k.startswith("US")]
            ud_codes = [k for k in self.animals if k.startswith("UD")]
            hx_codes = [k for k in self.animals if k.startswith("HX")]
            purchased = [k for k, v in self.animals.items() if v.get("is_purchased")]
            if us_codes:
                f.write(f"\nUnique unknown sire codes (US): {len(us_codes)}\n")
                for c in sorted(us_codes)[:30]:
                    f.write(f"  {c}\n")
            if ud_codes:
                f.write(f"\nUnique unknown dam codes (UD): {len(ud_codes)}\n")
                for c in sorted(ud_codes)[:30]:
                    f.write(f"  {c}\n")
            if hx_codes:
                f.write(f"\nHistorical stub codes (HX): {len(hx_codes)}\n")
                for c in sorted(hx_codes)[:30]:
                    f.write(f"  {c}\n")
            if purchased:
                f.write(f"\nPurchased animals (breed 00): {len(purchased)}\n")
                for c in sorted(purchased)[:30]:
                    f.write(f"  {c}\n")

        print(f"  Report: {path}")

    def write_conflicts_md(self, path):
        """Write duplicate lamb conflicts to a Markdown file for researcher review."""
        os.makedirs(os.path.dirname(path), exist_ok=True)

        identical = [d for d in self.duplicate_lambs if d["category"] == "IDENTICAL"]
        us_variant = [d for d in self.duplicate_lambs if d["category"] == "US_VARIANT"]
        conflicts = [d for d in self.duplicate_lambs if d["category"] == "CONFLICT"]

        with open(path, "w", encoding="utf-8") as f:
            f.write("# Duplicate Lamb Report\n\n")
            f.write("Lambs appearing more than once in PEDI data. "
                    "First occurrence was kept in all cases.\n\n")

            f.write(f"| Category | Count | Resolution |\n")
            f.write(f"|----------|------:|------------|\n")
            f.write(f"| IDENTICAL (same parents, same file) | {len(identical)} | Auto-deduped |\n")
            f.write(f"| US_VARIANT (US00/US18 sire prefix) | {len(us_variant)} | Auto-deduped |\n")
            f.write(f"| CONFLICT (different parents) | {len(conflicts)} | First row kept — **review needed** |\n")
            f.write(f"| **Total** | **{len(self.duplicate_lambs)}** | |\n\n")

            if conflicts:
                f.write("## Conflicts Requiring Researcher Review\n\n")
                f.write("These lambs have two PEDI rows with genuinely different sire/dam assignments.\n"
                        "The **first row** was kept. If the second row is correct, manual correction is needed.\n\n")

                for d in sorted(conflicts, key=lambda x: x["lamb_id10"]):
                    lamb = d["lamb_id10"]
                    first = d["first"]
                    dup = d["duplicate"]
                    f.write(f"### `{lamb}` (year={lamb[:4]}, breed={lamb[4:6]}, etag={lamb[6:]})\n\n")
                    f.write(f"| | Sire | Dam | Source | Row |\n")
                    f.write(f"|---|------|-----|--------|----:|\n")
                    f.write(f"| **Kept** | `{first['sire_id10']}` | `{first['dam_id10']}` "
                            f"| {first['source_file']} | {first['source_row']} |\n")
                    f.write(f"| Dropped | `{dup['sire_id10']}` | `{dup['dam_id10']}` "
                            f"| {dup['source_file']} | {dup['source_row']} |\n\n")

            if identical:
                f.write("## Identical Duplicates (auto-resolved)\n\n")
                f.write("Same lamb, same sire, same dam, same source file. Second row dropped.\n\n")
                f.write(f"| Lamb ID10 | Source | Rows |\n")
                f.write(f"|-----------|--------|------|\n")
                for d in sorted(identical, key=lambda x: x["lamb_id10"]):
                    f.write(f"| `{d['lamb_id10']}` | {d['first']['source_file']} "
                            f"| {d['first']['source_row']}, {d['duplicate']['source_row']} |\n")
                f.write("\n")

            if us_variant:
                f.write("## US-Code Variant Duplicates (auto-resolved)\n\n")
                f.write("Same lamb, same dam, sire differs only in US00 vs US18 prefix encoding.\n"
                        "These are re-listing blocks in PEDI1967-1968 for breed-90 crossbreds.\n"
                        "US18 codes were normalized to US00. Second row dropped.\n\n")
                f.write(f"| Lamb ID10 | Kept Sire | Dropped Sire | Source | Rows |\n")
                f.write(f"|-----------|-----------|-------------|--------|------|\n")
                for d in sorted(us_variant, key=lambda x: x["lamb_id10"]):
                    f.write(f"| `{d['lamb_id10']}` | `{d['first']['sire_id10']}` "
                            f"| `{d['duplicate']['sire_id10']}` | {d['first']['source_file']} "
                            f"| {d['first']['source_row']}, {d['duplicate']['source_row']} |\n")
                f.write("\n")

        print(f"  Conflicts: {path} ({len(conflicts)} conflicts, "
              f"{len(identical)} identical, {len(us_variant)} US-variants)")


# ---------------------------------------------------------------------------
# File processing — 8-digit format (1950-1997)
# ---------------------------------------------------------------------------

def process_8digit_file(src_path, out_dir, registry):
    """Process a 1950-1997 PEDI CSV with 8-digit composite IDs.

    Uses normalize_to_id10 to convert all IDs to YYYYBBEEEE.
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
            "LAMB_YYYY", "LAMB_BRD", "LAMB_ETAG",
            "SIRE_YYYY", "SIRE_BRD", "SIRE_ETAG",
            "DAM_YYYY", "DAM_BRD", "DAM_ETAG",
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

                lamb_p = normalize_to_id10(lamb_raw)
                sire_p = normalize_to_id10(sire_raw)
                dam_p = normalize_to_id10(dam_raw)

                if lamb_p is None:
                    parse_errors.append((rownum, "LAMB", lamb_raw))
                    continue

                lamb_id10 = lamb_p["id10"]

                # --- Sire ---
                if sire_p is None:
                    parse_errors.append((rownum, "SIRE", sire_raw))
                    sire_id10 = make_unknown_sire_code("00", "00", lamb_p["etag"])
                elif is_unknown_parent(sire_p):
                    dam_etag = dam_p["etag"] if dam_p and not is_unknown_parent(dam_p) else "0000"
                    sire_id10 = make_unknown_sire_code(
                        sire_p["breed"],
                        yr_2digit_from_yyyy(sire_p["yyyy"]),
                        dam_etag,
                    )
                    unknown_sires += 1
                else:
                    sire_id10 = sire_p["id10"]

                # --- Dam ---
                if dam_p is None:
                    parse_errors.append((rownum, "DAM", dam_raw))
                    dam_id10 = make_unknown_dam_code("00", "00", lamb_p["etag"])
                elif is_unknown_parent(dam_p):
                    sire_etag = sire_p["etag"] if sire_p and not is_unknown_parent(sire_p) else "0000"
                    dam_id10 = make_unknown_dam_code(
                        dam_p["breed"],
                        yr_2digit_from_yyyy(dam_p["yyyy"]),
                        sire_etag,
                    )
                    unknown_dams += 1
                else:
                    dam_id10 = dam_p["id10"]

                # --- Register animals ---
                # Normalize US18→US00 sire codes before registration
                sire_id10 = normalize_us_code(sire_id10)

                registry.add_animal(
                    lamb_id10, str(lamb_p["yyyy"]),
                    lamb_p["breed"], lamb_p["etag"],
                    "lamb", basename,
                )

                if sire_id10.startswith("US"):
                    registry.add_animal(
                        sire_id10,
                        str(sire_p["yyyy"]) if sire_p else "",
                        sire_p["breed"] if sire_p else "00",
                        "0000", "unknown_sire", basename,
                    )
                else:
                    registry.add_animal(
                        sire_id10,
                        str(sire_p["yyyy"]) if sire_p else "",
                        sire_p["breed"] if sire_p else "",
                        sire_p["etag"] if sire_p else "",
                        "sire", basename,
                    )

                if dam_id10.startswith("UD"):
                    registry.add_animal(
                        dam_id10,
                        str(dam_p["yyyy"]) if dam_p else "",
                        dam_p["breed"] if dam_p else "00",
                        "0000", "unknown_dam", basename,
                    )
                else:
                    registry.add_animal(
                        dam_id10,
                        str(dam_p["yyyy"]) if dam_p else "",
                        dam_p["breed"] if dam_p else "",
                        dam_p["etag"] if dam_p else "",
                        "dam", basename,
                    )

                registry.add_relationship(
                    lamb_id10, sire_id10, dam_id10, basename, rownum,
                )

                row["ID10"] = lamb_id10
                row["SIREID10"] = sire_id10
                row["DAMID10"] = dam_id10
                row["LAMB_YYYY"] = lamb_p["yyyy"]
                row["LAMB_BRD"] = lamb_p["breed"]
                row["LAMB_ETAG"] = lamb_p["etag"]
                row["SIRE_YYYY"] = sire_p["yyyy"] if sire_p else ""
                row["SIRE_BRD"] = sire_p["breed"] if sire_p else ""
                row["SIRE_ETAG"] = sire_p["etag"] if sire_p else ""
                row["DAM_YYYY"] = dam_p["yyyy"] if dam_p else ""
                row["DAM_BRD"] = dam_p["breed"] if dam_p else ""
                row["DAM_ETAG"] = dam_p["etag"] if dam_p else ""
                writer.writerow(row)
                written += 1

    with open(report_path, "w", encoding="utf-8") as rep:
        rep.write(f"=== PEDI Preprocessing Report: {basename} ===\n")
        rep.write(f"Format detected: 8digit\n\n")
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
# File processing — separated format (2000+)
# ---------------------------------------------------------------------------

def process_separated_file(src_path, out_dir, registry):
    """Process a 2000+ PEDI CSV with LAMBID/SIREID/DAMID columns.

    Uses normalize_to_id10_any to handle transitional (2000-2006) and
    modern (2007+) ID formats uniformly.
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
        extra = [c for c in ["ID10", "SIREID10", "DAMID10"] if c not in fieldnames]
        out_fields = fieldnames + extra

        os.makedirs(out_dir, exist_ok=True)
        with open(out_path, "w", newline="", encoding="utf-8") as outf:
            writer = csv.DictWriter(outf, fieldnames=out_fields, extrasaction="ignore")
            writer.writeheader()

            for rownum, row in enumerate(reader, start=1):
                total += 1

                lamb_raw = (row.get("LAMBID") or "").strip()
                sire_raw = (row.get("SIREID") or "").strip()
                dam_raw = (row.get("DAMID") or "").strip()

                # Normalize lamb
                lamb_p = normalize_to_id10_any(lamb_raw)
                if lamb_p is None:
                    parse_errors.append((rownum, "LAMBID", lamb_raw))
                    continue

                lamb_id10 = lamb_p["id10"]

                # Get breed/etag from row for US/UD code generation
                sbrd = (row.get("SBRD") or "").strip()
                dbrd = (row.get("DBRD") or "").strip()
                yr = (row.get("YR") or "").strip()
                dam_etag_short = (row.get("DAM") or "").strip()
                sire_etag_short = (row.get("SIRE") or "").strip()

                # Normalize sire
                if is_placeholder_id(sire_raw):
                    # Use short-form DAM etag (last 4 chars) for US code
                    dam_e = dam_etag_short[-4:].zfill(4) if dam_etag_short else "0000"
                    sire_id10 = make_unknown_sire_code(sbrd, yr, dam_e)
                    unknown_sires += 1
                    sire_p = None
                else:
                    sire_p = normalize_to_id10_any(sire_raw)
                    if sire_p is None:
                        parse_errors.append((rownum, "SIREID", sire_raw))
                        sire_id10 = make_unknown_sire_code("00", yr, lamb_p["etag"])
                    elif is_unknown_parent(sire_p):
                        dam_e = dam_etag_short[-4:].zfill(4) if dam_etag_short else "0000"
                        sire_id10 = make_unknown_sire_code(
                            sire_p["breed"],
                            yr_2digit_from_yyyy(sire_p["yyyy"]),
                            dam_e,
                        )
                        unknown_sires += 1
                    else:
                        sire_id10 = sire_p["id10"]

                # Normalize dam
                if is_placeholder_id(dam_raw):
                    sire_e = sire_etag_short[-4:].zfill(4) if sire_etag_short else "0000"
                    dam_id10 = make_unknown_dam_code(dbrd, yr, sire_e)
                    unknown_dams += 1
                    dam_p = None
                else:
                    dam_p = normalize_to_id10_any(dam_raw)
                    if dam_p is None:
                        parse_errors.append((rownum, "DAMID", dam_raw))
                        dam_id10 = make_unknown_dam_code("00", yr, lamb_p["etag"])
                    elif is_unknown_parent(dam_p):
                        sire_e = sire_etag_short[-4:].zfill(4) if sire_etag_short else "0000"
                        dam_id10 = make_unknown_dam_code(
                            dam_p["breed"],
                            yr_2digit_from_yyyy(dam_p["yyyy"]),
                            sire_e,
                        )
                        unknown_dams += 1
                    else:
                        dam_id10 = dam_p["id10"]

                # Register animals
                # Normalize US18→US00 sire codes before registration
                sire_id10 = normalize_us_code(sire_id10)

                lbrd = (row.get("LBRD") or row.get("BRD") or "").strip()
                lamb_etag_short = (row.get("LAMB") or row.get("ETAG") or "").strip()

                registry.add_animal(
                    lamb_id10, str(lamb_p["yyyy"]),
                    lbrd or lamb_p["breed"],
                    lamb_etag_short or lamb_p["etag"],
                    "lamb", basename,
                )

                if sire_id10.startswith("US"):
                    registry.add_animal(
                        sire_id10,
                        str(sire_p["yyyy"]) if sire_p else yr,
                        sbrd, "0000",
                        "unknown_sire", basename,
                    )
                elif sire_p:
                    registry.add_animal(
                        sire_id10,
                        str(sire_p["yyyy"]),
                        sbrd or sire_p["breed"],
                        sire_etag_short or sire_p["etag"],
                        "sire", basename,
                    )

                if dam_id10.startswith("UD"):
                    registry.add_animal(
                        dam_id10,
                        str(dam_p["yyyy"]) if dam_p else yr,
                        dbrd, "0000",
                        "unknown_dam", basename,
                    )
                elif dam_p:
                    registry.add_animal(
                        dam_id10,
                        str(dam_p["yyyy"]),
                        dbrd or dam_p["breed"],
                        dam_etag_short or dam_p["etag"],
                        "dam", basename,
                    )

                if lamb_id10 and sire_id10 and dam_id10:
                    registry.add_relationship(
                        lamb_id10, sire_id10, dam_id10, basename, rownum,
                    )

                row["ID10"] = lamb_id10
                row["SIREID10"] = sire_id10
                row["DAMID10"] = dam_id10
                writer.writerow(row)
                written += 1

    with open(report_path, "w", encoding="utf-8") as rep:
        rep.write(f"=== PEDI Preprocessing Report: {basename} ===\n")
        rep.write(f"Format detected: separated\n\n")
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
# Post-HX rewrite of preprocessed CSVs
# ---------------------------------------------------------------------------

def rewrite_preprocessed_with_hx(preproc_dir, hx_map):
    """After HX remapping, rewrite preprocessed CSVs to replace
    SIREID10/DAMID10 with HX equivalents and add PRE_CUTOFF flags."""
    if not hx_map:
        return

    preproc_path = Path(preproc_dir)
    csv_files = sorted(preproc_path.glob("*.preprocessed.csv"))

    for csv_file in csv_files:
        print(f"  Rewriting {csv_file.name} with HX mappings...")
        rows = []
        with open(csv_file, newline="", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            fieldnames = list(reader.fieldnames or [])
            for row in reader:
                rows.append(dict(row))

        extra_cols = []
        if "SIRE_PRE_CUTOFF" not in fieldnames:
            extra_cols.append("SIRE_PRE_CUTOFF")
        if "DAM_PRE_CUTOFF" not in fieldnames:
            extra_cols.append("DAM_PRE_CUTOFF")
        out_fields = fieldnames + extra_cols

        with open(csv_file, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=out_fields, extrasaction="ignore")
            writer.writeheader()
            for row in rows:
                sire_id = row.get("SIREID10", "")
                dam_id = row.get("DAMID10", "")

                sire_pre = "0"
                dam_pre = "0"

                if sire_id in hx_map:
                    row["SIREID10"] = hx_map[sire_id]
                    sire_pre = "1"
                if dam_id in hx_map:
                    row["DAMID10"] = hx_map[dam_id]
                    dam_pre = "1"

                row["SIRE_PRE_CUTOFF"] = sire_pre
                row["DAM_PRE_CUTOFF"] = dam_pre
                writer.writerow(row)

        print(f"    Done: {len(rows)} rows updated")


# ---------------------------------------------------------------------------
# Main driver
# ---------------------------------------------------------------------------

def find_pedi_files(path):
    """Find PEDI CSV files from a path (file or directory).

    Excludes PEDIGREE.csv (the undated master file).
    """
    p = Path(path)
    if p.is_file() and p.suffix.lower() == ".csv":
        return [p]
    if p.is_dir():
        files = sorted(p.glob("PEDI*.csv"))
        # Exclude PEDIGREE.csv
        files = [f for f in files if f.name.upper() != "PEDIGREE.CSV"]
        return files
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
        "--cutoff-year", type=int, default=DEFAULT_CUTOFF_YEAR,
        help=f"Full year cutoff for HX stubs (default: {DEFAULT_CUTOFF_YEAR}). "
             f"Animals born before this year get HX prefix IDs.",
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
    print(f"Cutoff year: {args.cutoff_year}")
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
            stats = process_8digit_file(str(f), str(preproc_dir), registry)
        else:
            stats = process_separated_file(str(f), str(preproc_dir), registry)

        stats["file"] = f.name
        stats["format"] = fmt
        file_stats.append(stats)
        print()

    # --- HX remapping ---
    print(f"Applying HX remapping (cutoff: {args.cutoff_year})...")
    hx_map = registry.apply_hx_remapping(args.cutoff_year)
    print(f"  {len(hx_map)} pre-cutoff animals remapped to HX IDs")

    # --- Rewrite preprocessed CSVs ---
    if hx_map:
        print(f"\nRewriting preprocessed CSVs with HX mappings...")
        rewrite_preprocessed_with_hx(str(preproc_dir), hx_map)

    # --- Write registry files ---
    print(f"\nWriting registry files...")
    registry.write_registry_csv(str(registry_dir / "pedi_registry.csv"))
    registry.write_relationships_csv(str(registry_dir / "pedi_relationships.csv"))
    registry.write_hx_map_csv(str(registry_dir / "hx_id_map.csv"), hx_map)
    registry.write_report(
        str(registry_dir / "registry_report.txt"),
        cutoff_year=args.cutoff_year,
        hx_map=hx_map,
    )
    registry.write_conflicts_md(
        str(registry_dir / "duplicate_conflicts.md"),
    )

    # --- Summary ---
    print(f"\n{'='*60}")
    print(f"SUMMARY")
    print(f"{'='*60}")
    print(f"Files processed: {len(file_stats)}")
    print(f"Total unique animals in registry: {len(registry.animals)}")
    hx_count = len(hx_map)
    us_count = len([k for k in registry.animals if k.startswith('US')])
    ud_count = len([k for k in registry.animals if k.startswith('UD')])
    purchased_count = len([k for k, v in registry.animals.items() if v.get("is_purchased")])
    print(f"  Historical stubs (HX): {hx_count}")
    print(f"  Unknown sires (US): {us_count}")
    print(f"  Unknown dams (UD): {ud_count}")
    print(f"  Purchased (breed 00): {purchased_count}")
    print(f"  Regular animals: {len(registry.animals) - hx_count - us_count - ud_count}")
    print(f"Total relationships recorded: {len(registry.relationships)}")
    if registry.duplicate_lambs:
        ident = len([d for d in registry.duplicate_lambs if d["category"] == "IDENTICAL"])
        usvar = len([d for d in registry.duplicate_lambs if d["category"] == "US_VARIANT"])
        confl = len([d for d in registry.duplicate_lambs if d["category"] == "CONFLICT"])
        print(f"  Duplicates resolved: {len(registry.duplicate_lambs)} "
              f"({ident} identical, {usvar} US-variant, {confl} conflicts)")
    total_parse_errors = 0
    for s in file_stats:
        print(f"  {s['file']}: {s['written']}/{s['total']} rows, "
              f"{s['unknown_sires']} US, {s['unknown_dams']} UD, "
              f"{s['parse_errors']} errors")
        total_parse_errors += s['parse_errors']
    if total_parse_errors:
        print(f"\n⚠ Total parse errors across all files: {total_parse_errors}")
    print(f"{'='*60}\n")

    bad_ids = [k for k in registry.animals if len(k) != 10]
    if bad_ids:
        print(f"⚠ WARNING: {len(bad_ids)} animals have ID10 != 10 chars!")
        for b in bad_ids[:20]:
            print(f"  '{b}' (len={len(b)})")
    else:
        print("✓ All ID10 values are exactly 10 characters.")


if __name__ == "__main__":
    main()
