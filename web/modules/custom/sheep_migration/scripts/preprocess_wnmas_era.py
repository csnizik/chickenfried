#!/usr/bin/env python3
"""
preprocess_wnmas_era.py — Phase 0c: Preprocess WNMAS (weaning/measurements) files
for pre-2010 era data.

PROBLEM: WNMAS1950 identifies animals by ETAG (4-digit ear tag) + LBRD + YR,
not by ID10. We must resolve each row to its canonical ID10 using the PEDI
registry built by build_pedi_registry.py.

WEANING DATE: Not stored directly. Calculated as:
  weaning_date = birth_date (from LAMB preprocessed) + WNAGE (days)

OUTPUT: WNMAS{YEAR}.preprocessed.csv with:
  - ID10 column (resolved from registry)
  - WEANING_DATE column (calculated)
  - All original columns preserved
  - LEGACY_JSON column (unmapped fields as JSON object)

FIELD MAPPING (to ObservationRecord):
  Dedicated fields:    WNWT → field_s_body_weight, HORN → field_s_horn,
                       FACE → field_s_face, WEANING_DATE → field_s_observation_date
  Life stage event:    "Weaning" (constant in migration YAML)
  Legacy JSON:         Everything else not mapped to a dedicated field

USAGE:
  python3 preprocess_wnmas_era.py WNMAS1950.csv \\
    --registry path/to/pedi_registry.csv \\
    --lamb-preprocessed path/to/LAMB1950.preprocessed.csv
"""

import csv
import json
import os
import sys
import re
import argparse
from datetime import datetime, timedelta
from pathlib import Path
from collections import defaultdict

YEAR_RE = re.compile(r"(\d{4})")

# Fields that map to dedicated ObservationRecord base fields.
# These are NOT included in legacy JSON.
DEDICATED_FIELDS = {
    "UID",          # migration source ID
    "ID10",         # added by preprocessor
    "WEANING_DATE", # added by preprocessor (calculated)
    "WNWT",        # → field_s_body_weight
    "HORN",        # → field_s_horn
    "FACE",        # → field_s_face (not present in 1950 headers but future-proof)
    "YR",          # → constant in YAML
    "ETAG",        # identity (used to resolve ID10)
    "LBRD",        # identity (used to resolve ID10)
}

# Fields that already live on other entities (sheep_record, annual_assignment,
# lamb_card). Do NOT import into ObservationRecord at all — not even as legacy JSON.
# They are here for documentation; we still exclude them from legacy JSON to
# avoid confusion about source of truth.
FIELDS_ON_OTHER_ENTITIES = {
    "SEX",         # sheep_record.field_s_sex
    "LINE",        # sheep_record.field_s_line
    "CLR",         # sheep_record.field_s_color
    "DAM",         # sheep_record.field_s_dam (via lineage)
    "DBRD",        # sheep_record dam breed (via PEDI)
    "BPEN",        # annual_assignment.field_s_breeding_pen OR lamb_card.field_s_pen
    "BAND",        # annual_assignment.field_s_band
    "DISP",        # sheep_record.field_s_preliminary_disp
    "JAW",         # future: observation or lamb_card TBD
    "BRWT",        # sheep_record.field_s_birth_weight
    "ADAM",         # adoptive dam — future handling
    "LNDAM",       # line of dam — derived from dam's sheep_record
    "LB1",         # breed indicator — used for ID resolution only
    "DB1",         # dam breed indicator — used for ID resolution only
}

# Everything NOT in DEDICATED_FIELDS and NOT in FIELDS_ON_OTHER_ENTITIES
# goes into legacy JSON.


def extract_year(path):
    m = YEAR_RE.search(os.path.basename(path))
    return m.group(1) if m else None


def clean(v):
    if v is None:
        return ""
    return str(v).strip()


def build_etag_to_id10_map(registry_path, year_2digit):
    """Build lookup: (year_2digit, breed_2digit, etag_4digit) → ID10."""
    lookup = {}
    with open(registry_path, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            id10 = clean(row.get("id10", ""))
            birth_year = clean(row.get("birth_year", ""))
            breed = clean(row.get("breed", ""))
            etag = clean(row.get("etag", ""))
            id_type = clean(row.get("id_type", ""))

            # Only map real animals (lambs, sires, dams) — skip US/UD synthetics
            if id_type in ("unknown_sire", "unknown_dam"):
                continue
            if not etag or etag == "0000":
                continue

            # Registry birth_year is 2-digit for 8-digit format
            key = (birth_year, breed, etag)
            if key not in lookup:
                lookup[key] = id10

    return lookup


def build_birth_date_map(lamb_preprocessed_path):
    """Build lookup: ID10 → birth date string (YYYY-MM-DD) from LAMB preprocessed."""
    dates = {}
    with open(lamb_preprocessed_path, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            id10 = clean(row.get("ID10", ""))
            datebn = clean(row.get("DATEBN", ""))
            if id10 and datebn:
                dates[id10] = datebn
    return dates


def calculate_weaning_date(birth_date_str, wnage_str):
    """Calculate weaning date = birth_date + WNAGE days."""
    if not birth_date_str or not wnage_str:
        return ""
    try:
        wnage = int(float(wnage_str))
        if wnage <= 0:
            return ""
        birth_date = datetime.strptime(birth_date_str, "%Y-%m-%d")
        weaning_date = birth_date + timedelta(days=wnage)
        return weaning_date.strftime("%Y-%m-%d")
    except (ValueError, TypeError):
        return ""


def process_file(src_path, registry_path, lamb_preprocessed_path, out_dir):
    basename = os.path.basename(src_path)
    year = extract_year(basename) or "0000"
    year_2digit = year[2:4]  # "50" from "1950"
    out_name = f"WNMAS{year}.preprocessed.csv"
    out_path = os.path.join(out_dir, out_name)
    report_path = os.path.join(out_dir, f"WNMAS{year}.report.txt")

    # Build lookups
    print(f"  Loading PEDI registry from {registry_path}...")
    etag_map = build_etag_to_id10_map(registry_path, year_2digit)
    print(f"  Registry entries: {len(etag_map)}")

    print(f"  Loading birth dates from {lamb_preprocessed_path}...")
    birth_dates = build_birth_date_map(lamb_preprocessed_path)
    print(f"  Birth dates loaded: {len(birth_dates)}")

    # Process
    os.makedirs(out_dir, exist_ok=True)
    total = 0
    resolved = 0
    unresolved = 0
    unresolved_rows = []
    date_calculated = 0
    date_missing_birth = 0
    date_missing_wnage = 0

    with open(src_path, newline="", encoding="utf-8", errors="replace") as inf:
        reader = csv.DictReader(inf)
        source_fields = list(reader.fieldnames or [])

        # Determine which fields go to legacy JSON
        legacy_candidates = set(source_fields) - DEDICATED_FIELDS - FIELDS_ON_OTHER_ENTITIES
        legacy_field_names = sorted(legacy_candidates)

        out_fields = ["UID", "ID10", "WEANING_DATE", "WNWT", "HORN", "FACE",
                       "WNAGE", "LEGACY_JSON"] + \
                      ["MIGRATION_NOTE"]

        with open(out_path, "w", newline="", encoding="utf-8") as outf:
            writer = csv.DictWriter(outf, fieldnames=out_fields)
            writer.writeheader()

            for row in reader:
                total += 1
                etag = clean(row.get("ETAG", ""))
                lbrd = clean(row.get("LBRD", ""))
                notes = []

                # Resolve ETAG → ID10
                # Key: (year_2digit, breed, etag_padded)
                etag_padded = etag.zfill(4)
                key = (year_2digit, lbrd, etag_padded)
                id10 = etag_map.get(key, "")

                if not id10:
                    # Try constructing directly: "19" + YR + LBRD + ETAG(4)
                    candidate = f"19{year_2digit}{lbrd.zfill(2)}{etag_padded}"
                    if len(candidate) == 10:
                        id10 = candidate
                        notes.append(f"ID10 constructed directly (not in registry)")

                if id10:
                    resolved += 1
                else:
                    unresolved += 1
                    unresolved_rows.append({
                        "uid": clean(row.get("UID", "")),
                        "etag": etag,
                        "lbrd": lbrd,
                        "yr": year_2digit,
                    })
                    notes.append("UNRESOLVED: Could not determine ID10")

                # Calculate weaning date
                birth_date = birth_dates.get(id10, "")
                wnage = clean(row.get("WNAGE", ""))
                weaning_date = ""

                if birth_date and wnage:
                    weaning_date = calculate_weaning_date(birth_date, wnage)
                    if weaning_date:
                        date_calculated += 1
                    else:
                        notes.append("Weaning date calc failed")
                elif not birth_date:
                    date_missing_birth += 1
                    notes.append("No birth date found for weaning date calc")
                elif not wnage:
                    date_missing_wnage += 1
                    notes.append("No WNAGE for weaning date calc")

                # Build legacy JSON from unmapped fields
                legacy = {}
                for fname in legacy_field_names:
                    val = clean(row.get(fname, ""))
                    if val and val != "0" and val != "0.0" and val != ".":
                        legacy[fname] = val

                out_row = {
                    "UID": clean(row.get("UID", "")),
                    "ID10": id10,
                    "WEANING_DATE": weaning_date,
                    "WNWT": clean(row.get("WNWT", "")),
                    "HORN": clean(row.get("HORN", "")),
                    "FACE": clean(row.get("FACE", "")),
                    "WNAGE": wnage,
                    "LEGACY_JSON": json.dumps(legacy) if legacy else "",
                    "MIGRATION_NOTE": "; ".join(notes) if notes else "",
                }
                writer.writerow(out_row)

    # Report
    with open(report_path, "w", encoding="utf-8") as rep:
        rep.write(f"=== WNMAS Preprocessing Report: {basename} ===\n")
        rep.write(f"Year: {year}\n\n")
        rep.write(f"Total rows: {total}\n")
        rep.write(f"ID10 resolved: {resolved}\n")
        rep.write(f"ID10 unresolved: {unresolved}\n\n")
        rep.write(f"Weaning dates calculated: {date_calculated}\n")
        rep.write(f"Missing birth date (no LAMB match): {date_missing_birth}\n")
        rep.write(f"Missing WNAGE: {date_missing_wnage}\n\n")
        rep.write(f"Legacy JSON fields: {legacy_field_names}\n\n")

        if unresolved_rows:
            rep.write(f"--- Unresolved rows (first 50) ---\n")
            for r in unresolved_rows[:50]:
                rep.write(f"  UID={r['uid']} ETAG={r['etag']} LBRD={r['lbrd']} YR={r['yr']}\n")

    print(f"  Output: {out_path} ({total} rows)")
    print(f"  Resolved: {resolved}/{total}, Unresolved: {unresolved}")
    print(f"  Weaning dates: {date_calculated} calculated, {date_missing_birth} missing birth, {date_missing_wnage} missing WNAGE")
    print(f"  Report: {report_path}")


def main():
    parser = argparse.ArgumentParser(
        description="Preprocess WNMAS files for pre-2010 era migration",
    )
    parser.add_argument("input", help="Path to WNMAS CSV file")
    parser.add_argument("--registry",
                        required=True,
                        help="Path to pedi_registry.csv")
    parser.add_argument("--lamb-preprocessed",
                        required=True,
                        help="Path to LAMB{YEAR}.preprocessed.csv")
    parser.add_argument("--outdir",
                        help="Output directory (default: same as input)")
    args = parser.parse_args()

    src = Path(args.input)
    if not src.is_file():
        print(f"ERROR: Source file not found: {args.input}")
        sys.exit(1)

    out_dir = args.outdir or str(src.parent)
    print(f"Processing: {src.name}")
    process_file(str(src), args.registry, args.lamb_preprocessed, out_dir)


if __name__ == "__main__":
    main()
