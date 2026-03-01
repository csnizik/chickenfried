#!/usr/bin/env python3
"""
Preprocess INV CSV files to omit any rows whose ID10 is not exactly 10 characters long.

Usage:
  python3 preprocess_inv.py path/to/INV2010.csv
  python3 preprocess_inv.py path/to/INV2010.csv --outdir path/to/preprocessed

Outputs:
  - Writes a CSV with the same name into a `preprocessed` subdirectory by default.
  - Writes a QA report alongside the preprocessed CSV (counts, sample omitted rows).

This script only uses the Python stdlib and streams rows so it should be safe for large files.
"""

import csv
import os
import re
import argparse

PLACEHOLDERS = {"", ".", "NO.DATA", "NOT.FOUND", "LAMBNOTFOUND", "NOLAMBFOUND", "NO.BANDS", "NO.BAND", "NOTFOUND", "NIL", "NA"}
ALNUM_RE = re.compile(r"[^A-Z0-9]")


def clean_val(v):
    if v is None:
        return ""
    s = str(v).strip().upper()
    if s in PLACEHOLDERS:
        return ""
    return s


def normalize_id10(v):
    """Normalize ID10 for length checking: trim, uppercase, remove non-alphanumeric."""
    s = clean_val(v)
    if not s:
        return ""
    return ALNUM_RE.sub('', s)


def process_file(inpath, outdir=None):
    if outdir is None:
        outdir = os.path.join(os.path.dirname(inpath), 'preprocessed')
    os.makedirs(outdir, exist_ok=True)
    basename = os.path.basename(inpath)
    outpath = os.path.join(outdir, basename.replace('.csv', '.preprocessed.csv'))
    report_path = os.path.join(outdir, basename.replace('.csv', '.report.txt'))

    total = 0
    written = 0
    omitted = 0
    omitted_examples = []

    with open(inpath, newline='', encoding='utf-8') as inf, open(outpath, 'w', newline='', encoding='utf-8') as outf:
        reader = csv.DictReader(inf)
        fieldnames = list(reader.fieldnames) if reader.fieldnames else []
        # ensure ID10 exists in header
        if 'ID10' not in fieldnames:
            # add ID10 at front
            fieldnames.insert(0, 'ID10')
        writer = csv.DictWriter(outf, fieldnames=fieldnames, extrasaction='ignore')
        writer.writeheader()

        for rownum, row in enumerate(reader, start=1):
            total += 1
            raw = row.get('ID10', '')
            norm = normalize_id10(raw)
            # If ID10 normalized length is not exactly 10, omit the row
            if len(norm) != 10:
                omitted += 1
                if len(omitted_examples) < 200:
                    omitted_examples.append((rownum, raw, norm))
                continue

            # preserve original row, but ensure the ID10 column contains the normalized representation
            row['ID10'] = norm
            writer.writerow(row)
            written += 1

    with open(report_path, 'w', encoding='utf-8') as rep:
        rep.write(f"Input file: {inpath}\n")
        rep.write(f"Output file: {outpath}\n")
        rep.write(f"Total rows processed: {total}\n")
        rep.write(f"Rows written: {written}\n")
        rep.write(f"Rows omitted (ID10 length != 10): {omitted}\n")
        if omitted_examples:
            rep.write('\nSample omitted rows (rownum, raw, normalized):\n')
            for r in omitted_examples[:200]:
                rep.write(f"  row {r[0]} raw='{r[1]}' normalized='{r[2]}'\n")

    print(f"Wrote preprocessed CSV to: {outpath}")
    print(f"Wrote QA report to: {report_path}")
    print(f"Total: {total}, written: {written}, omitted: {omitted}")


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Preprocess INV CSVs and omit rows with invalid ID10')
    parser.add_argument('input', help='Path to INV CSV file')
    parser.add_argument('--outdir', help='Output directory (defaults to <csvdir>/preprocessed)')
    args = parser.parse_args()
    process_file(args.input, args.outdir)
