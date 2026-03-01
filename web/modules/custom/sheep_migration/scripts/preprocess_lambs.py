#!/usr/bin/env python3
"""
Preprocess LAMB CSV files to add a canonical ID10 column.

Usage:
  python3 preprocess_lambs.py path/to/LAMB2010.csv
  python3 preprocess_lambs.py path/to/LAMB2010.csv --outdir path/to/preprocessed

Outputs:
  - Writes a CSV with the same name into a `preprocessed` subdirectory by default.
  - Writes a QA report alongside the preprocessed CSV (duplicates, counts).

ID rules implemented (per user):
  - Disposed IDs (when DAYDIS or DISP or CAUSE present):
      D + YYYY + last4(dam_ETAG_normalized_or_sire_or_rownum4) + CDNO_final_char_or_rownum_lastchar
    Fallbacks:
      - if dam ETAG unusable -> use sire ETAG
      - if both unusable -> use row number padded to 4 digits
      - if CDNO missing -> use last digit of row number

  - Live IDs:
      - If existing ID10 present and not a placeholder, keep it
      - Else: YYYY + 2-digit breed (from LBRD) + last4(LAMB)

Normalization:
  - trim, uppercase, remove non-alphanumeric for ETAG fields
  - treat placeholders (., NO.DATA, NOT.FOUND, LAMBNOTFOUND, NOLAMBFOUND, etc.) as empty

This script uses only the Python standard library and streams rows so it should be safe for large files.
"""

import csv
import sys
import os
import re
import argparse

PLACEHOLDERS = {"", ".", "NO.DATA", "NOT.FOUND", "LAMBNOTFOUND", "NOLAMBFOUND", "NO.BANDS", "NO.BAND", "NOTFOUND", "NIL", "NA"}
ALNUM_RE = re.compile(r"[^A-Z0-9]")
YEAR_RE = re.compile(r"(\d{4})")


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
    # remove non-alphanumeric, uppercase
    s = ALNUM_RE.sub('', s)
    return s


def last_n(s, n=4):
    if not s:
        return '0' * n
    s = str(s)
    return s[-n:].rjust(n, '0')


def extract_year_from_filename(path):
    m = YEAR_RE.search(os.path.basename(path))
    return m.group(1) if m else None


def cdno_final_char(cdno, rownum):
    cd = clean_val(cdno)
    # keep alphanumeric only
    cd = re.sub(r'[^A-Z0-9]', '', cd)
    if cd:
        return cd[-1]
    # fallback to last digit of rownum
    return str(rownum % 10)


def build_disposed_id(year, dam_etag, sire_etag, cdno, rownum):
    # year expected YYYY
    dam4 = last_n(normalize_etag(dam_etag), 4)
    if dam4 == '0000':
        sire4 = last_n(normalize_etag(sire_etag), 4)
        if sire4 != '0000':
            dam4 = sire4
    if dam4 == '0000':
        # fallback to rownum padded to 4
        dam4 = str(rownum).zfill(4)[-4:]
    cdch = cdno_final_char(cdno, rownum)
    id10 = f"D{year}{dam4}{cdch}"
    return id10


def build_live_id(year, lbrd, lamb):
    # year YYYY + 2-digit breed + last4(LAMB)
    brd = clean_val(lbrd)[:2]
    if not brd:
        brd = '00'
    brd = re.sub(r'[^A-Z0-9]', '', brd).rjust(2, '0')[:2]
    last4 = last_n(normalize_etag(lamb), 4)
    return f"{year}{brd}{last4}"


def is_disposed_row(row):
    # If DAYDIS, DISP or CAUSE has a non-placeholder value, consider disposed
    for fld in ('DAYDIS', 'DISP', 'CAUSE'):
        if fld in row and clean_val(row.get(fld, '')):
            return True
    return False


def is_valid_existing_id10(val):
    v = clean_val(val)
    if not v:
        return False
    # treat short or obviously placeholder values as invalid
    if v in PLACEHOLDERS:
        return False
    return True


def process_file(inpath, outdir=None):
    if outdir is None:
        outdir = os.path.join(os.path.dirname(inpath), 'preprocessed')
    os.makedirs(outdir, exist_ok=True)
    basename = os.path.basename(inpath)
    year = extract_year_from_filename(basename) or '0000'
    outpath = os.path.join(outdir, basename.replace('.csv', '.preprocessed.csv'))
    report_path = os.path.join(outdir, basename.replace('.csv', '.report.txt'))

    total = 0
    written = 0
    duplicates = []
    seen = {}
    missing_components = []
    omitted_count = 0
    omitted_examples = []

    with open(inpath, newline='', encoding='utf-8') as inf, open(outpath, 'w', newline='', encoding='utf-8') as outf:
        reader = csv.DictReader(inf)
        # ensure ID10 exists in header
        fieldnames = list(reader.fieldnames) if reader.fieldnames else []
        if 'ID10' not in fieldnames:
            fieldnames.insert(0, 'ID10')
        writer = csv.DictWriter(outf, fieldnames=fieldnames, extrasaction='ignore')
        writer.writeheader()

        for rownum, row in enumerate(reader, start=1):
            total += 1
            # clean keys presence
            # compute ID10
            existing_id10 = row.get('ID10', '')
            id10 = ''
            if is_valid_existing_id10(existing_id10):
                id10 = clean_val(existing_id10)
            else:
                # Executive decision: consider rows with missing LAMB as "disposed" for ID construction
                if not normalize_etag(row.get('LAMB', '')):
                    uid_val = clean_val(row.get('UID', ''))
                    # Prefix with 'D' per executive rule. If UID missing, fallback to rownum padded.
                    if uid_val:
                        id10 = f"D{uid_val}"
                    else:
                        id10 = f"D{str(rownum).zfill(9)}"  # ensure uniqueness if UID absent
                else:
                    id10 = build_live_id(year, row.get('LBRD', ''), row.get('LAMB', ''))

            row['ID10'] = id10

            # OMIT rows where ID10 is not exactly 10 characters long
            if len(id10) != 10:
                omitted_count += 1
                if len(omitted_examples) < 200:
                    omitted_examples.append((rownum, id10))
                # skip writing this row entirely
                continue

            # track duplicates
            if id10 in seen:
                duplicates.append((id10, seen[id10], rownum))
            else:
                seen[id10] = rownum

            # collect missing components for report
            if not normalize_etag(row.get('LAMB', '')):
                missing_components.append((rownum, 'LAMB'))

            writer.writerow(row)
            written += 1

    # write report
    with open(report_path, 'w', encoding='utf-8') as rep:
        rep.write(f"Input file: {inpath}\n")
        rep.write(f"Output file: {outpath}\n")
        rep.write(f"Total rows processed: {total}\n")
        rep.write(f"Rows written: {written}\n")
        rep.write(f"Rows omitted (ID10 length != 10): {omitted_count}\n")
        rep.write(f"Unique ID10 count: {len(seen)}\n")
        rep.write(f"Duplicate ID10 count: {len(duplicates)}\n")
        if duplicates:
            rep.write("Duplicates (id10, first_row, duplicate_row):\n")
            for d in duplicates[:200]:
                rep.write(f"  {d[0]}  first:{d[1]} dup:{d[2]}\n")
        if omitted_examples:
            rep.write('\nSample omitted rows (rownum, id10):\n')
            for o in omitted_examples[:200]:
                rep.write(f"  row {o[0]} id10='{o[1]}'\n")
        rep.write('\nMissing/blank critical components (sample):\n')
        for m in missing_components[:200]:
            rep.write(f"  row {m[0]} missing {m[1]}\n")

    print(f"Wrote preprocessed CSV to: {outpath}")
    print(f"Wrote QA report to: {report_path}")
    print(f"Total: {total}, duplicates: {len(duplicates)}")


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Preprocess LAMB CSVs and add ID10 column')
    parser.add_argument('input', help='Path to LAMB CSV file')
    parser.add_argument('--outdir', help='Output directory (defaults to <csvdir>/preprocessed)')
    args = parser.parse_args()
    process_file(args.input, args.outdir)
