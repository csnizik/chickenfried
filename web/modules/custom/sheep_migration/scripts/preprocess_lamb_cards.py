#!/usr/bin/env python3
"""
preprocess_lamb_cards.py — Phase 0d: Group LAMB rows into Lamb Card rows.

Each output row represents one ewe's lambing event (one LambCard entity).
Twins/triplets from the same dam collapse into a single row with pipe-separated
lamb IDs in the LAMB_IDS column.

INPUT:  Preprocessed LAMB CSV (from preprocess_lamb_era.py)
OUTPUT: LAMBCARDS_{YEAR}.preprocessed.csv

Grouping key: DAMID10 (unique per dam per year in single-year files)

Fields carried forward per card:
  - From first lamb in group: DAMID10, SIREID10, TB, MILK, PEN, DATEBN/DAYBN, Y1-Y5
  - Aggregated: LAMB_IDS (pipe-separated), LITTER_SIZE (count)
  - Card UID: LC_{YEAR}_{DAMID10} for migration idempotency

USAGE:
  python3 preprocess_lamb_cards.py path/to/LAMB1950.preprocessed.csv
  python3 preprocess_lamb_cards.py path/to/preprocessed/ --year 1950
"""

import csv
import os
import sys
import re
import argparse
from pathlib import Path
from collections import defaultdict, OrderedDict

YEAR_RE = re.compile(r"(\d{4})")


def extract_year(path):
    m = YEAR_RE.search(os.path.basename(path))
    return m.group(1) if m else None


def clean(v):
    if v is None:
        return ""
    return str(v).strip()


def process_file(src_path, out_dir):
    basename = os.path.basename(src_path)
    year = extract_year(basename) or "0000"
    out_name = f"LAMBCARDS_{year}.preprocessed.csv"
    out_path = os.path.join(out_dir, out_name)
    report_path = os.path.join(out_dir, f"LAMBCARDS_{year}.report.txt")

    # --- Pass 1: Group live lambs by DAMID10 ---
    # Only live lambs (ID10 starts with "19") get lamb cards.
    # Disposed lambs (D-prefix) have no identity — skip them.
    dam_groups = OrderedDict()  # DAMID10 → list of row dicts

    with open(src_path, newline="", encoding="utf-8", errors="replace") as f:
        reader = csv.DictReader(f)
        source_fields = list(reader.fieldnames or [])

        for row in reader:
            id10 = clean(row.get("ID10", ""))
            dam_id10 = clean(row.get("DAMID10", ""))

            # Skip disposed lambs
            if not id10.startswith("19"):
                continue
            # Skip if no dam
            if not dam_id10:
                continue

            if dam_id10 not in dam_groups:
                dam_groups[dam_id10] = []
            dam_groups[dam_id10].append(row)

    # --- Pass 2: Build one output row per dam (lambing event) ---
    os.makedirs(out_dir, exist_ok=True)

    out_fields = [
        "CARD_UID",        # Unique key: LC_{YEAR}_{DAMID10}
        "DAMID10",         # Dam entity reference
        "SIREID10",        # Sire entity reference (from first lamb)
        "LAMB_IDS",        # Pipe-separated lamb ID10s
        "LITTER_SIZE",     # Count of lambs
        "TB",              # Type of birth (ewe-level)
        "MILK",            # Milk score (ewe-level)
        "PEN",             # Breeding pen
        "DATEBN",          # Lambing date (from first lamb)
        "DAYBN",           # Julian day of birth (from first lamb)
        "Y1",              # Present for lambing
        "Y2",              # Lambed
        "Y3",              # Y3 trait
        "Y4",              # Y4 trait
        "Y5",              # Y5 trait
        "YEAR",            # Record year
    ]

    total_cards = 0
    sire_conflicts = []
    tb_conflicts = []

    with open(out_path, "w", newline="", encoding="utf-8") as outf:
        writer = csv.DictWriter(outf, fieldnames=out_fields)
        writer.writeheader()

        for dam_id10, lambs in dam_groups.items():
            total_cards += 1
            first = lambs[0]

            # Collect all lamb ID10s
            lamb_ids = [clean(r.get("ID10", "")) for r in lambs]

            # Sire: should be same across litter. Log conflicts.
            sire_ids = set(clean(r.get("SIREID10", "")) for r in lambs)
            sire_ids.discard("")
            if len(sire_ids) > 1:
                sire_conflicts.append((dam_id10, sire_ids))
            sire_id10 = clean(first.get("SIREID10", ""))

            # TB: should be same across litter. Log conflicts.
            tbs = set(clean(r.get("TB", "")) for r in lambs)
            tbs.discard("")
            if len(tbs) > 1:
                tb_conflicts.append((dam_id10, tbs))

            card_row = {
                "CARD_UID": f"LC_{year}_{dam_id10}",
                "DAMID10": dam_id10,
                "SIREID10": sire_id10,
                "LAMB_IDS": "|".join(lamb_ids),
                "LITTER_SIZE": str(len(lambs)),
                "TB": clean(first.get("TB", "")),
                "MILK": clean(first.get("MILK", "")),
                "PEN": clean(first.get("PEN", "")),
                "DATEBN": clean(first.get("DATEBN", "")),
                "DAYBN": clean(first.get("DAYBN", "")),
                "Y1": clean(first.get("Y1", "")),
                "Y2": clean(first.get("Y2", "")),
                "Y3": clean(first.get("Y3", "")),
                "Y4": clean(first.get("Y4", "")),
                "Y5": clean(first.get("Y5", "")),
                "YEAR": year,
            }
            writer.writerow(card_row)

    # --- QA Report ---
    litter_sizes = defaultdict(int)
    for lambs in dam_groups.values():
        litter_sizes[len(lambs)] += 1

    with open(report_path, "w", encoding="utf-8") as rep:
        rep.write(f"=== Lamb Cards Preprocessing Report: {basename} ===\n")
        rep.write(f"Year: {year}\n\n")
        rep.write(f"Total lamb cards: {total_cards}\n")
        rep.write(f"Litter sizes:\n")
        for size in sorted(litter_sizes.keys()):
            rep.write(f"  {size}: {litter_sizes[size]} cards\n")
        rep.write(f"\nSire conflicts (multiple sires per litter): {len(sire_conflicts)}\n")
        if sire_conflicts:
            for dam, sires in sire_conflicts[:50]:
                rep.write(f"  {dam}: {sires}\n")
        rep.write(f"\nTB conflicts (multiple TB values per litter): {len(tb_conflicts)}\n")
        if tb_conflicts:
            for dam, tbs in tb_conflicts[:50]:
                rep.write(f"  {dam}: {tbs}\n")

    print(f"  Lamb cards: {out_path} ({total_cards} cards)")
    print(f"  Litter sizes: {dict(sorted(litter_sizes.items()))}")
    print(f"  Sire conflicts: {len(sire_conflicts)}, TB conflicts: {len(tb_conflicts)}")
    print(f"  Report: {report_path}")

    return {
        "total_cards": total_cards,
        "litter_sizes": dict(litter_sizes),
        "sire_conflicts": len(sire_conflicts),
        "tb_conflicts": len(tb_conflicts),
    }


def find_files(path):
    p = Path(path)
    if p.is_file():
        return [p]
    if p.is_dir():
        return sorted(p.glob("LAMB*.preprocessed.csv"))
    return []


def main():
    parser = argparse.ArgumentParser(
        description="Group preprocessed LAMB rows into Lamb Card rows",
    )
    parser.add_argument(
        "input",
        help="Path to preprocessed LAMB CSV or directory containing them",
    )
    parser.add_argument(
        "--outdir",
        help="Output directory (default: same as input directory)",
    )
    args = parser.parse_args()

    files = find_files(args.input)
    if not files:
        print(f"ERROR: No preprocessed LAMB CSV files found at: {args.input}")
        sys.exit(1)

    for f in files:
        out_dir = args.outdir or str(f.parent)
        print(f"Processing: {f.name}")
        process_file(str(f), out_dir)
        print()


if __name__ == "__main__":
    main()
