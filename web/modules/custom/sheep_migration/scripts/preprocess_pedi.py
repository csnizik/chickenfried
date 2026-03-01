#!/usr/bin/env python3
"""Preprocess PEDI CSVs.

For each PEDI*.csv in the pedi data directory this script will:
- Detect placeholder/unknown SIREID values and replace them with a 10-character
  composite code: "US" + SBRD + YR + last4(DAM).
  This groups unknown sires into one unique unknown-sire entity per litter
  (SBRD + YR + DAM last4).
- Same with (rare) unknown dam, using composite code starting with "UD"
- Write the modified CSV files to a `preprocessed/` subdirectory next to the
  source CSVs, preserving headers and row ordering.

Usage: run from repository root or call the script directly. No external
dependencies beyond the Python standard library.
"""

from pathlib import Path
import csv
import re
import argparse

# script lives in: web/modules/custom/sheep_migration/scripts/
# data is in:       web/modules/custom/sheep_migration/data/pedi/
PARENT = Path(__file__).resolve().parent.parent / "data" / "pedi"
OUT_DIR_NAME = "preprocessed"

# Placeholder detection for SIREID values
RE_LETTER_ZERO = re.compile(r"^[A-Za-z]0{3,4}$")
RE_ALL_ZERO = re.compile(r"^0+$")


def is_placeholder_sireid(val: str) -> bool:
    if val is None:
        return True
    s = str(val).strip()
    if s == "":
        return True
    # common patterns: all zeros, letter+0000 (e.g., A0000), long zero-padded
    if RE_ALL_ZERO.fullmatch(s):
        return True
    if RE_LETTER_ZERO.fullmatch(s):
        return True
    return False


def is_placeholder_damid(val: str) -> bool:
    if val is None:
        return True
    s = str(val).strip()
    if s == "":
        return True
    # common patterns: all zeros, letter+0000 (e.g., X0000), long zero-padded
    if RE_ALL_ZERO.fullmatch(s):
        return True
    if RE_LETTER_ZERO.fullmatch(s):
        return True
    return False


def normalize_part(val: str, length: int, pad_left: bool = True) -> str:
    s = (val or "").strip()
    if len(s) >= length:
        return s[-length:]
    if pad_left:
        return s.zfill(length)
    return s.rjust(length, "0")


def make_unknown_sire_code(sbrd: str, yr: str, dam: str) -> str:
    # Build a 10-char code: 'US' + SBRD(2) + YR(last2) + DAM(last4)
    # SBRD: take first up to 2 chars, uppercase, pad left with '0' if needed
    sbrd_part = (sbrd or "").strip().upper()[:2]
    if sbrd_part == "":
        sbrd_part = "00"
    elif len(sbrd_part) == 1:
        sbrd_part = sbrd_part.zfill(2)

    # YR: use last 2 characters of year string (e.g., '2025' -> '25'), pad to 2
    yr_part = (yr or "").strip()
    if yr_part == "":
        yr_part = "00"
    else:
        yr_part = yr_part[-2:].zfill(2)

    # DAM: last 4 characters, pad left with zeros if short
    dam_part = (dam or "").strip()
    if dam_part == "":
        dam_part = "0000"
    else:
        dam_part = dam_part[-4:]
        if len(dam_part) < 4:
            dam_part = dam_part.zfill(4)

    code = f"US{sbrd_part}{yr_part}{dam_part}"
    # Guarantee length 10 by truncating or padding as a last resort
    if len(code) != 10:
        code = (code + ("0" * 10))[:10]
    return code


def make_unknown_dam_code(sbrd: str, yr: str, sire: str) -> str:
    # Build a 10-char code: 'UD' + SBRD(2) + YR(last2) + SIRE(last4)
    # SBRD: take first up to 2 chars, uppercase, pad left with '0' if needed
    sbrd_part = (sbrd or "").strip().upper()[:2]
    if sbrd_part == "":
        sbrd_part = "00"
    elif len(sbrd_part) == 1:
        sbrd_part = sbrd_part.zfill(2)

    # YR: use last 2 characters of year string (e.g., '2025' -> '25'), pad to 2
    yr_part = (yr or "").strip()
    if yr_part == "":
        yr_part = "00"
    else:
        yr_part = yr_part[-2:].zfill(2)

    # SIRE: last 4 characters, pad left with zeros if short
    sire_part = (sire or "").strip()
    if sire_part == "":
        sire_part = "0000"
    else:
        sire_part = sire_part[-4:]
        if len(sire_part) < 4:
            sire_part = sire_part.zfill(4)

    code = f"UD{sbrd_part}{yr_part}{sire_part}"
    # Guarantee length 10 by truncating or padding as a last resort
    if len(code) != 10:
        code = (code + ("0" * 10))[:10]
    return code


def process_file(src_path: Path, out_dir: Path):
    out_dir.mkdir(parents=True, exist_ok=True)
    out_path = out_dir / f"{src_path.stem}.preprocessed{src_path.suffix}"
    with src_path.open("r", encoding="utf-8", errors="replace") as inf, out_path.open("w", encoding="utf-8", newline="") as outf:
        reader = csv.reader(inf)
        writer = csv.writer(outf)
        try:
            header = next(reader)
        except StopIteration:
            return
        writer.writerow(header)
        # find indices
        idx = {name: i for i, name in enumerate(header)}
        i_SIREID = idx.get("SIREID")
        i_DAMID = idx.get("DAMID")
        i_SBRD = idx.get("SBRD")
        i_DBRD = idx.get("DBRD")
        i_YR = idx.get("YR")
        i_DAM = idx.get("DAM")
        i_SIRE = idx.get("SIRE")
        for row in reader:
            # Process unknown sires
            if i_SIREID is not None and i_SIREID < len(row):
                cur = row[i_SIREID]
                if is_placeholder_sireid(cur):
                    # gather components
                    sbrd = row[i_SBRD] if (i_SBRD is not None and i_SBRD < len(row)) else ""
                    yr = row[i_YR] if (i_YR is not None and i_YR < len(row)) else ""
                    dam = row[i_DAM] if (i_DAM is not None and i_DAM < len(row)) else ""
                    new_code = make_unknown_sire_code(sbrd, yr, dam)
                    row[i_SIREID] = new_code
            # Process unknown dams
            if i_DAMID is not None and i_DAMID < len(row):
                cur = row[i_DAMID]
                if is_placeholder_damid(cur):
                    # gather components
                    dbrd = row[i_DBRD] if (i_DBRD is not None and i_DBRD < len(row)) else ""
                    yr = row[i_YR] if (i_YR is not None and i_YR < len(row)) else ""
                    sire = row[i_SIRE] if (i_SIRE is not None and i_SIRE < len(row)) else ""
                    new_code = make_unknown_dam_code(dbrd, yr, sire)
                    row[i_DAMID] = new_code
            writer.writerow(row)
    print(f"Wrote: {out_path}")


def main(src_dir: Path = PARENT, out_subdir: str = OUT_DIR_NAME):
    out_dir = src_dir / out_subdir
    csvs = sorted(src_dir.glob("PEDI*.csv"))
    if not csvs:
        print("No PEDI CSVs found in:", src_dir)
        return
    for f in csvs:
        process_file(f, out_dir)
    print("All files processed. Output directory:", out_dir)


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description="Preprocess PEDI CSVs and replace placeholder SIREID values")
    ap.add_argument("--src", help="PEDI CSV directory (defaults to data/pedi next to this script)", default=str(PARENT))
    ap.add_argument("--out", help="Output subdirectory name under the src dir", default=OUT_DIR_NAME)
    args = ap.parse_args()
    main(Path(args.src), args.out)
