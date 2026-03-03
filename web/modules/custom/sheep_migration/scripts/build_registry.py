#!/usr/bin/env python3
"""
build_registry.py — PEDI registry builder (v2, no normalization).

Collects every unique raw animal identifier from PEDI CSV files.
No ID normalization. The verbatim value IS the key.

Skips zeros/placeholders (0, 00000000, 0000000000, etc.).

Usage:
  python3 build_registry.py /path/to/pedi/directory/
  python3 build_registry.py /path/to/single_file.csv

Outputs (in current directory):
  pedi_registry.csv    — one row per unique raw identifier
  parse_errors.csv     — rows that couldn't be read
"""

import csv
import os
import sys
from pathlib import Path


ZERO_PATTERNS = frozenset({"0", "00", "000", "0000", "00000", "000000",
                           "0000000", "00000000", "000000000", "0000000000"})


def is_zero(val):
    return val in ZERO_PATTERNS


def detect_columns(headers):
    """Return (lamb_col, sire_col, dam_col) or None."""
    h = {x.strip().upper(): x.strip() for x in headers}
    if "LAMBID" in h:
        return (h["LAMBID"], h.get("SIREID", ""), h.get("DAMID", ""))
    if "LAMB" in h and "SIRE" in h and "DAM" in h:
        return (h["LAMB"], h["SIRE"], h["DAM"])
    return None


def process_file(filepath, animals):
    """Process one PEDI CSV. Returns list of parse errors."""
    basename = os.path.basename(filepath)
    errors = []

    with open(filepath, newline="", encoding="utf-8", errors="replace") as f:
        reader = csv.DictReader(f)
        cols = detect_columns(reader.fieldnames)
        if cols is None:
            print(f"  SKIP {basename}: unknown format")
            return errors

        lamb_col, sire_col, dam_col = cols

        for rownum, row in enumerate(reader, start=2):
            for col, role in [(lamb_col, "lamb"), (sire_col, "sire"), (dam_col, "dam")]:
                if not col:
                    continue
                raw = row.get(col, "").strip()
                if not raw or is_zero(raw):
                    continue

                # Minimal validation: must be digits, dots, or spaces
                cleaned = raw.replace(" ", "").replace(".", "")
                if not cleaned.isdigit():
                    errors.append((basename, rownum, role, raw))
                    continue

                if raw not in animals:
                    animals[raw] = {"sources": set(), "roles": set()}
                animals[raw]["sources"].add(basename)
                animals[raw]["roles"].add(role)

    return errors


def main():
    if len(sys.argv) < 2:
        print("Usage: python3 build_registry.py <path_to_pedi_dir_or_file>")
        sys.exit(1)

    target = Path(sys.argv[1])
    if target.is_file():
        files = [target]
    elif target.is_dir():
        files = sorted(target.glob("PEDI*.csv"))
        if not files:
            files = sorted(target.glob("sample_PEDI*.csv"))
    else:
        print(f"Not found: {target}")
        sys.exit(1)

    if not files:
        print("No PEDI CSV files found.")
        sys.exit(1)

    out_dir = Path(sys.argv[2]) if len(sys.argv) > 2 else Path(".")

    print(f"Processing {len(files)} PEDI files...")
    animals = {}
    all_errors = []

    for f in files:
        before = len(animals)
        errs = process_file(str(f), animals)
        all_errors.extend(errs)
        delta = len(animals) - before
        err_note = f" ({len(errs)} errors)" if errs else ""
        print(f"  {f.name} → +{delta} new, {len(animals)} total{err_note}")

    # --- Write registry ---
    reg_path = out_dir / "pedi_registry.csv"
    with open(reg_path, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["raw_id", "source_files", "roles"])
        for raw_id in sorted(animals.keys()):
            a = animals[raw_id]
            w.writerow([
                raw_id,
                "|".join(sorted(a["sources"])),
                "|".join(sorted(a["roles"])),
            ])
    print(f"\nRegistry: {reg_path} ({len(animals)} unique raw IDs)")

    # --- Errors ---
    err_path = out_dir / "parse_errors.csv"
    with open(err_path, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["source_file", "row", "role", "raw_value"])
        for e in all_errors:
            w.writerow(e)
    print(f"Parse errors: {err_path} ({len(all_errors)} errors)")

    # --- Summary ---
    lamb_only = sum(1 for a in animals.values() if a["roles"] == {"lamb"})
    sire_or_dam = sum(1 for a in animals.values() if a["roles"] & {"sire", "dam"})
    print(f"\nSummary:")
    print(f"  Total unique raw IDs: {len(animals)}")
    print(f"  Appearing only as lambs: {lamb_only}")
    print(f"  Referenced as sire/dam: {sire_or_dam}")


if __name__ == "__main__":
    main()
