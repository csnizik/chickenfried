#!/usr/bin/env python3
"""
build_pedi_lamb_registry.py — Cross-reference LAMB tables with PEDI registry.

Reads every LAMB CSV (1950-2025), matches each lamb row to the PEDI registry,
generates D-tags for deceased untagged animals, and outputs a unified
pedi_lamb_registry.csv ready for Drupal migration.

LIVING vs DECEASED determination:
  Living  = no value in DISP AND no value in DAYDIS
  Deceased = has value in DISP OR has value in DAYDIS

D-TAG generation (for deceased animals without an ear tag):
  Format: D + YYYY(4) + last4(dam_etag) + CDNO_last_char = 10 chars
  Fallbacks:
    - dam etag unusable → use sire etag
    - both unusable → use row number padded to 4 digits
    - CDNO missing or >9 causing duplicate → use last digit of row number

OUTPUTS:
  registry/pedi_lamb_registry.csv  — One row per lamb with unified schema
  registry/pedi_lamb_report.md     — Discrepancy report

USAGE:
  python3 build_pedi_lamb_registry.py \\
    --pedi-dir path/to/pedigree-csv \\
    --lamb-dir path/to/lamb-csv
"""

import csv
import os
import sys
import re
import argparse
from pathlib import Path
from collections import defaultdict

# Import normalization functions from build_pedi_registry
# The script expects build_pedi_registry.py to be in the same directory
# or on the Python path.
SCRIPT_DIR = Path(__file__).resolve().parent
sys.path.insert(0, str(SCRIPT_DIR))
from build_pedi_registry import normalize_to_id10, normalize_to_id10_any, normalize_us_code


# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

# Fields that indicate an animal is deceased
DISP_FIELDS = ["DISP"]
DAYDIS_FIELDS = ["DAYDIS"]

# Column name mappings across eras (LAMB table field → canonical name)
# Each entry is a list of possible column names in priority order.
FIELD_MAP = {
    "lamb":        ["LAMB"],
    "lamb_breed":  ["LBRD", "LB1"],
    "sire":        ["SIRE"],
    "sire_breed":  ["SBRD", "SB1"],
    "dam":         ["DAM"],
    "dam_breed":   ["DBRD", "DB1"],
    "yr":          ["YR"],
    "sex":         ["SEX"],
    "cdno":        ["CDNO"],
    "birth_weight":["BRWT"],
    "color":       ["CLR"],
    "jaw":         ["JAW"],
    "type_of_birth":["TB"],
    "abnormality": ["ABN", "ABNL"],
    "band":        ["BAND"],
    "birth_day_julian": ["DAYBRN", "DAYBN"],
    "birth_date":  ["DATEBN"],
    "disp":        ["DISP"],
    "daydis":      ["DAYDIS"],
    "cause":       ["CAUSE"],
    "depth":       ["DEPTH"],
    "dystocia":    ["DYST"],
    "entropion":   ["ENTR"],
    "line":        ["LINE"],
    "shed":        ["SHED"],
    "stm":         ["STM"],  # NOT STMD or STMS
    "teat":        ["TEAT"],
    "breeding_pen":["BPEN", "PEN"],
    "inbreeding":  ["INBRL", "INBL", "LINBR"],
    "adam":        ["ADAM"],
    "milk":        ["MILK"],
    "bday":        ["BDAY", "DATE"],
}


def resolve_field(row, field_key):
    """Look up a value from a LAMB row using the field map.

    Tries each possible column name for the given canonical field.
    Returns the stripped value or empty string.
    """
    for col_name in FIELD_MAP.get(field_key, []):
        val = row.get(col_name, "").strip()
        if val:
            return val
    return ""


def is_deceased(row):
    """Determine if a lamb is deceased based on DISP and DAYDIS fields."""
    disp = resolve_field(row, "disp")
    daydis = resolve_field(row, "daydis")
    return bool(disp) or bool(daydis)

def has_ear_tag(row):
    """Determine if a lamb was assigned an ear tag (LAMB field has a real value).

    LAMB field formats across eras:
      - Pure numeric: '1234', '0377'  (etag only)
      - Alpha-prefixed: 'X0119', 'A0640' (breed letter + 4-digit etag)
      - No-tag placeholders: '', '0', '00000', 'X0000', 'Z0000', '.', '...'
    """
    lamb_raw = resolve_field(row, "lamb")
    if not lamb_raw:
        return False
    s = lamb_raw.strip()
    if not s:
        return False
    # Dot placeholders = no tag
    if re.fullmatch(r"\.+", s):
        return False
    # Strip leading alpha prefix to get the numeric portion
    numeric = s.lstrip("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz")
    if not numeric:
        return False
    # All zeros = no tag
    if re.fullmatch(r"0+", numeric):
        return False
    return True


def extract_etag_from_lamb(lamb_raw):
    """Extract the numeric ear tag portion from a LAMB field value.

    Handles:
      'X0119'  → '0119'
      'A0640'  → '0640'
      '1234'   → '1234'
      '0377'   → '0377'
      '.'      → ''

    Returns the numeric string, or '' if invalid.
    """
    s = str(lamb_raw).strip()
    if not s:
        return ""
    # Dot placeholders = no tag
    if re.fullmatch(r"\.+", s):
        return ""
    # Strip leading alpha characters (breed prefix)
    numeric = s.lstrip("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz")
    if not numeric or not numeric.isdigit():
        return ""
    if re.fullmatch(r"0+", numeric):
        return ""
    return numeric

# ---------------------------------------------------------------------------
# D-tag generation
# ---------------------------------------------------------------------------

def generate_d_tag(year, dam_etag, sire_etag, cdno, row_num, seen_d_tags):
    """Generate a synthetic D-tag for a deceased untagged lamb.

    Format: D + YYYY(4) + last4(parent_etag) + CDNO_last_char = 10 chars

    Args:
        year:       4-digit birth year string
        dam_etag:   normalized 4-digit dam etag (may be empty/unusable)
        sire_etag:  normalized 4-digit sire etag (fallback)
        cdno:       CDNO value from LAMB row
        row_num:    row number in LAMB file (for final fallback)
        seen_d_tags: set of already-generated D-tags (for dedup)

    Returns:
        10-char D-tag string, or None if generation fails.
    """
    yyyy = str(year).strip()[:4].zfill(4)

    # Determine parent etag (4 chars)
    parent_etag = ""
    if dam_etag and len(dam_etag) >= 4 and dam_etag != "0000":
        parent_etag = dam_etag[-4:]
    elif sire_etag and len(sire_etag) >= 4 and sire_etag != "0000":
        parent_etag = sire_etag[-4:]
    else:
        # Fallback: use row number padded to 4 digits
        parent_etag = str(row_num).zfill(4)[-4:]

    # Determine CDNO suffix (1 char)
    cdno_char = ""
    if cdno and cdno.strip():
        cdno_s = cdno.strip()
        try:
            cdno_int = int(cdno_s)
            if cdno_int <= 9:
                cdno_char = str(cdno_int)
            else:
                # CDNO > 9: use last digit of row number
                cdno_char = str(row_num)[-1]
        except ValueError:
            cdno_char = str(row_num)[-1]
    else:
        # CDNO missing: use last digit of row number
        cdno_char = str(row_num)[-1]

    d_tag = f"D{yyyy}{parent_etag}{cdno_char}"

    # Check for duplicate
    if d_tag in seen_d_tags:
        # Resolve by using last digit of row number instead
        cdno_char = str(row_num)[-1]
        d_tag = f"D{yyyy}{parent_etag}{cdno_char}"
        if d_tag in seen_d_tags:
            # Still duplicate — try incrementing suffix
            for suffix in "0123456789":
                candidate = f"D{yyyy}{parent_etag}{suffix}"
                if candidate not in seen_d_tags:
                    d_tag = candidate
                    break
            else:
                return None  # Cannot resolve

    if len(d_tag) != 10:
        return None

    seen_d_tags.add(d_tag)
    return d_tag


# ---------------------------------------------------------------------------
# LAMB file discovery and year extraction
# ---------------------------------------------------------------------------

def find_lamb_files(path):
    """Find LAMB CSV files from a directory."""
    p = Path(path)
    if p.is_file():
        return [p]
    if p.is_dir():
        files = sorted(p.glob("LAMB*.csv"))
        # Exclude any non-year files
        return [f for f in files if re.match(r"LAMB\d{4}\.csv", f.name)]
    return []


def extract_year_from_filename(filename):
    """Extract 4-digit year from LAMB filename."""
    m = re.search(r"LAMB(\d{4})", filename)
    return int(m.group(1)) if m else None


# ---------------------------------------------------------------------------
# Load PEDI registry and relationships
# ---------------------------------------------------------------------------

def load_pedi_registry(registry_path):
    """Load pedi_registry.csv into a dict keyed by id10."""
    registry = {}
    with open(registry_path, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            id10 = row["id10"]
            registry[id10] = row
    return registry


def load_pedi_relationships(rel_path):
    """Load pedi_relationships.csv into a dict keyed by lamb_id10."""
    rels = {}
    with open(rel_path, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            lamb = row["lamb_id10"]
            rels[lamb] = row
    return rels


# ---------------------------------------------------------------------------
# Normalize a LAMB row's IDs
# ---------------------------------------------------------------------------

def normalize_lamb_id(raw, file_year):
    """Normalize a LAMB/SIRE/DAM value to ID10.

    Tries full-ID normalization first (for composite 8/9/10 digit IDs).
    Returns parsed dict or None.
    """
    s = str(raw).strip()
    if not s:
        return None

    # Space-to-zero fix
    if " " in s:
        s = s.replace(" ", "0")

    # Dot notation fix
    if "." in s:
        parts = s.split(".")
        if len(parts) == 2 and len(parts[0]) == 2 and len(parts[1]) == 5:
            yy, rest = parts
            s = f"{yy}{rest[:2]}0{rest[2:]}"

    result = normalize_to_id10(s)
    if result:
        return result

    # Fallback: already-10-digit IDs (1950-2099 range)
    if len(s) == 10 and s.isdigit():
        yyyy = int(s[0:4])
        if 1950 <= yyyy <= 2099:
            return {
                "id10": s,
                "yyyy": yyyy,
                "breed": s[4:6],
                "etag": s[6:10],
                "raw": s,
            }

    return None

def assemble_id10(year, breed, etag):
    """Assemble an ID10 from year + breed + ear tag components.

    LAMB tables store IDs as separate fields:
      YR (2-digit or 4-digit), LBRD/SBRD/DBRD (breed), LAMB/SIRE/DAM (ear tag).

    Args:
        year:  4-digit year (int or str)
        breed: breed code (str, 1-2 chars)
        etag:  ear tag number (str, may have alpha prefix or be '.')

    Returns:
        dict with id10, yyyy, breed, etag, raw — or None if invalid.
    """
    yyyy = str(year).strip()
    if not yyyy:
        return None
    try:
        yyyy_int = int(yyyy)
    except ValueError:
        return None

    brd = str(breed).strip()
    # Dot placeholders = invalid breed
    if not brd or re.fullmatch(r"\.+", brd):
        return None
    brd = brd.zfill(2)[-2:]
    # Ensure breed is numeric
    if not brd.isdigit():
        return None

    # Strip alpha prefix from etag (e.g., 'X0119' → '0119', 'A0640' → '0640')
    tag_raw = str(etag).strip()
    # Dot placeholders = invalid etag
    if not tag_raw or re.fullmatch(r"\.+", tag_raw):
        return None
    tag = tag_raw.lstrip("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz")

    if not tag or not tag.isdigit():
        return None
    if re.fullmatch(r"0+", tag):
        # All zeros = unknown/placeholder
        return None

    tag = tag.zfill(4)[-4:]  # Pad to 4, take last 4

    id10 = f"{yyyy_int:04d}{brd}{tag}"
    if len(id10) != 10:
        return None

    return {
        "id10": id10,
        "yyyy": yyyy_int,
        "breed": brd,
        "etag": tag,
        "raw": f"{yyyy}/{brd}/{tag_raw}",
    }


def resolve_parent_id10(raw_id, breed_raw, etag_raw, year, file_year):
    """Resolve a parent (sire/dam) to ID10.

    Strategy:
      1. If raw_id looks like a composite ID (8+ digits), try normalize_lamb_id
      2. Otherwise, assemble from year + breed + etag components
      3. For LAMB tables, SIRE/DAM fields are often short ear tags

    Returns:
        (id10_str, etag_str) tuple, or ("", "") if unresolvable.
    """
    # First: try the raw composite ID (works for 2000+ era SIREID/DAMID style)
    if raw_id:
        parsed = normalize_lamb_id(raw_id, file_year)
        if parsed:
            return parsed["id10"], parsed["etag"]

    # Second: assemble from components (LAMB table's separate fields)
    if etag_raw and breed_raw:
        assembled = assemble_id10(year, breed_raw, etag_raw)
        if assembled:
            return assembled["id10"], assembled["etag"]

    # If only raw_id exists and looks numeric, try assembly with breed
    if raw_id and breed_raw:
        assembled = assemble_id10(year, breed_raw, raw_id)
        if assembled:
            return assembled["id10"], assembled["etag"]

    return "", ""


# ---------------------------------------------------------------------------
# Main processing
# ---------------------------------------------------------------------------

OUTPUT_FIELDS = [
    "id10", "birth_year", "dam", "sire", "breed", "etag", "cdno", "sex",
    "inbreeding_coefficient", "color", "jaw", "type_of_birth",
    "abnormality_code", "band", "birth_date_julian", "disposal",
    "disposal_date_julian", "depth", "dystocia", "entropion", "line",
    "shed", "subtype_mating", "teat", "breeding_pen",
    "is_unidentified_sire", "is_unidentified_dam", "is_deceased_no_etag",
    "is_historical_stub", "legacy_id", "is_purchased", "pedi_sources",
    "lamb_sources",
]


def process_lamb_file(lamb_path, file_year, pedi_reg, pedi_rels,
                      output_rows, seen_d_tags, discrepancies, stats):
    """Process a single LAMB CSV file.

    For each row:
      1. Determine living vs deceased
      2. Normalize IDs
      3. Match to pedi_registry (living) or generate D-tag (deceased no etag)
      4. Build output row
      5. Flag discrepancies
    """
    basename = lamb_path.name
    file_year_str = str(file_year)

    with open(lamb_path, newline="", encoding="utf-8", errors="replace") as f:
        reader = csv.DictReader(f)
        if not reader.fieldnames:
            discrepancies.append({
                "type": "NO_HEADERS", "file": basename,
                "detail": "No headers found in file",
            })
            return

        for row_num, row in enumerate(reader, start=1):
            stats["total_rows"] += 1

            # --- Resolve core fields ---
            lamb_raw = resolve_field(row, "lamb")
            dam_raw = resolve_field(row, "dam")
            sire_raw = resolve_field(row, "sire")
            cdno = resolve_field(row, "cdno")
            sex = resolve_field(row, "sex")

            # --- Determine year ---
            yr_raw = resolve_field(row, "yr")
            # Some files have 2-digit year, some 4-digit
            if yr_raw:
                try:
                    yr_int = int(yr_raw)
                    if yr_int < 100:
                        row_year = 1900 + yr_int if yr_int >= 50 else 2000 + yr_int
                    else:
                        row_year = yr_int
                except ValueError:
                    row_year = file_year
            else:
                row_year = file_year

            # --- Parent IDs ---
            # Deferred: we resolve parents AFTER determining lamb's ID10 match.
            # For pedi-matched lambs, pedi_relationships is authoritative.
            # For D-tagged lambs, we attempt composite normalization of DAM/SIRE.
            dam_breed_raw = resolve_field(row, "dam_breed")
            sire_breed_raw = resolve_field(row, "sire_breed")
            dam_id10 = ""
            sire_id10 = ""
            dam_etag = ""
            sire_etag = ""

            # --- Determine living vs deceased ---
            deceased = is_deceased(row)
            has_tag = has_ear_tag(row)

            # --- Determine ID10 ---
            lamb_id10 = ""
            is_deceased_no_etag = False
            matched_pedi = False

            if has_tag:
                # Has ear tag — build ID10 from components: YYYY + LBRD + LAMB
                lamb_breed_raw = resolve_field(row, "lamb_breed")

                # Strategy: try composite normalization first (handles 8/10-digit
                # IDs in some eras), then fall back to component assembly.
                lamb_parsed = normalize_lamb_id(lamb_raw, file_year)
                if lamb_parsed:
                    lamb_id10 = lamb_parsed["id10"]
                    lamb_breed = lamb_parsed["breed"]
                    lamb_etag = lamb_parsed["etag"]
                else:
                    # Assemble from components: YYYY + LBRD + LAMB(zfill 4)
                    assembled = assemble_id10(row_year, lamb_breed_raw, lamb_raw)
                    if assembled:
                        lamb_id10 = assembled["id10"]
                        lamb_breed = assembled["breed"]
                        lamb_etag = assembled["etag"]
                    else:
                        discrepancies.append({
                            "type": "LAMB_ID_PARSE_ERROR",
                            "file": basename, "row": row_num,
                            "detail": f"Cannot build ID10: LAMB='{lamb_raw}' "
                                      f"LBRD='{lamb_breed_raw}' YR={row_year}",
                        })
                        stats["parse_errors"] += 1
                        continue

                # Look up in pedi_registry
                if lamb_id10 in pedi_reg:
                    matched_pedi = True
                    pedi_entry = pedi_reg[lamb_id10]

                    # Verify year matches
                    pedi_year = pedi_entry.get("birth_year", "")
                    if pedi_year and str(row_year) != str(pedi_year):
                        discrepancies.append({
                            "type": "YEAR_MISMATCH",
                            "file": basename, "row": row_num,
                            "detail": f"id10={lamb_id10}: LAMB file year={row_year}, "
                                      f"pedi birth_year={pedi_year}",
                        })
                        stats["year_mismatches"] += 1
                else:
                    # Not in pedi_registry — flag
                    if not deceased:
                        discrepancies.append({
                            "type": "LIVING_NOT_IN_PEDI",
                            "file": basename, "row": row_num,
                            "detail": f"Living lamb id10={lamb_id10} not found in pedi_registry",
                        })
                        stats["living_not_in_pedi"] += 1

            else:
                # No ear tag — must be deceased
                if not deceased:
                    # No tag AND not deceased? Flag as anomaly
                    discrepancies.append({
                        "type": "NO_TAG_NOT_DECEASED",
                        "file": basename, "row": row_num,
                        "detail": f"LAMB='{lamb_raw}' has no ear tag and no DISP/DAYDIS",
                    })
                    stats["no_tag_not_deceased"] += 1
                    deceased = True

                # For D-tag generation we need dam/sire etags.
                # Try composite normalization first, then extract from alpha-prefixed.
                if dam_raw:
                    dam_parsed = normalize_lamb_id(dam_raw, file_year)
                    if dam_parsed:
                        dam_etag = dam_parsed["etag"]
                    else:
                        # Try extracting numeric portion from alpha-prefixed ID
                        dam_etag = extract_etag_from_lamb(dam_raw)
                if sire_raw:
                    sire_parsed = normalize_lamb_id(sire_raw, file_year)
                    if sire_parsed:
                        sire_etag = sire_parsed["etag"]
                    else:
                        sire_etag = extract_etag_from_lamb(sire_raw)

                # Generate D-tag
                d_tag = generate_d_tag(
                    row_year, dam_etag, sire_etag,
                    cdno, row_num, seen_d_tags,
                )
                if d_tag:
                    lamb_id10 = d_tag
                    is_deceased_no_etag = True
                    lamb_breed = resolve_field(row, "lamb_breed")
                    lamb_etag = ""
                    stats["d_tags_generated"] += 1
                else:
                    discrepancies.append({
                        "type": "D_TAG_GENERATION_FAILED",
                        "file": basename, "row": row_num,
                        "detail": f"Cannot generate D-tag: dam_etag={dam_etag}, "
                                  f"sire_etag={sire_etag}, cdno={cdno}",
                    })
                    stats["d_tag_failures"] += 1
                    continue

            # --- Resolve parent IDs ---
            # Strategy: pedi_relationships is authoritative for pedi-matched lambs.
            # For D-tagged lambs, resolve from LAMB row's composite DAM/SIRE fields.

            if matched_pedi and lamb_id10 in pedi_rels:
                pedi_rel = pedi_rels[lamb_id10]
                dam_id10 = pedi_rel["dam_id10"]
                sire_id10 = pedi_rel["sire_id10"]
            elif not is_deceased_no_etag and lamb_id10 in pedi_rels:
                # Deceased with etag — also use pedi_relationships
                pedi_rel = pedi_rels[lamb_id10]
                dam_id10 = pedi_rel["dam_id10"]
                sire_id10 = pedi_rel["sire_id10"]
            else:
                # D-tagged or unmatched: resolve from LAMB row
                if dam_raw:
                    parsed = normalize_lamb_id(dam_raw, file_year)
                    if parsed:
                        dam_id10 = parsed["id10"]
                if sire_raw:
                    parsed = normalize_lamb_id(sire_raw, file_year)
                    if parsed:
                        sire_id10 = parsed["id10"]
                        sire_id10 = normalize_us_code(sire_id10)

            is_unidentified_sire = "1" if sire_id10.startswith("US") else "0"
            is_unidentified_dam = "1" if dam_id10.startswith("UD") else "0"

            # --- Lookup pedi_registry metadata ---
            pedi_entry = pedi_reg.get(lamb_id10, {})
            is_historical_stub = pedi_entry.get("is_historical_stub", "0")
            is_purchased = pedi_entry.get("is_purchased", "0")
            legacy_id = pedi_entry.get("original_id10", "")
            pedi_sources = pedi_entry.get("sources", "")

            # --- Build output row ---
            out_row = {
                "id10": lamb_id10,
                "birth_year": str(row_year),
                "dam": dam_id10,
                "sire": sire_id10,
                "breed": (resolve_field(row, "lamb_breed")
                          or pedi_entry.get("breed", "")),
                "etag": lamb_etag if not is_deceased_no_etag else "",
                "cdno": cdno,
                "sex": sex,
                "inbreeding_coefficient": resolve_field(row, "inbreeding"),
                "color": resolve_field(row, "color"),
                "jaw": resolve_field(row, "jaw"),
                "type_of_birth": resolve_field(row, "type_of_birth"),
                "abnormality_code": resolve_field(row, "abnormality"),
                "band": resolve_field(row, "band"),
                "birth_date_julian": resolve_field(row, "birth_day_julian"),
                "disposal": resolve_field(row, "disp"),
                "disposal_date_julian": resolve_field(row, "daydis"),
                "depth": resolve_field(row, "depth"),
                "dystocia": resolve_field(row, "dystocia"),
                "entropion": resolve_field(row, "entropion"),
                "line": resolve_field(row, "line"),
                "shed": resolve_field(row, "shed"),
                "subtype_mating": resolve_field(row, "stm"),
                "teat": resolve_field(row, "teat"),
                "breeding_pen": resolve_field(row, "breeding_pen"),
                "is_unidentified_sire": is_unidentified_sire,
                "is_unidentified_dam": is_unidentified_dam,
                "is_deceased_no_etag": "1" if is_deceased_no_etag else "0",
                "is_historical_stub": is_historical_stub,
                "legacy_id": legacy_id,
                "is_purchased": is_purchased,
                "pedi_sources": pedi_sources,
                "lamb_sources": basename,
            }
            output_rows.append(out_row)

            if matched_pedi:
                stats["matched_pedi"] += 1
            if deceased:
                stats["deceased"] += 1
            if not deceased:
                stats["living"] += 1


# ---------------------------------------------------------------------------
# Discrepancy report (Markdown)
# ---------------------------------------------------------------------------

def write_report(report_path, stats, discrepancies, pedi_reg, matched_ids):
    """Write a Markdown discrepancy report."""
    with open(report_path, "w", encoding="utf-8") as f:
        f.write("# PEDI ↔ LAMB Cross-Reference Report\n\n")

        f.write("## Summary\n\n")
        f.write(f"| Metric | Count |\n")
        f.write(f"|--------|------:|\n")
        for key in sorted(stats.keys()):
            f.write(f"| {key} | {stats[key]} |\n")
        f.write("\n")

        # PEDI entries NOT found in any LAMB file
        # (Only check non-US/UD/HX/D entries that are id_type="lamb")
        pedi_lambs_not_in_lamb = []
        for id10, entry in pedi_reg.items():
            if entry.get("id_type") == "lamb" and id10 not in matched_ids:
                pedi_lambs_not_in_lamb.append(id10)

        if pedi_lambs_not_in_lamb:
            f.write(f"## PEDI Registry Lambs Not Found in LAMB Tables "
                    f"({len(pedi_lambs_not_in_lamb)})\n\n")
            f.write("These animals exist in pedi_registry as `id_type=lamb` "
                    "but were not matched to any LAMB CSV row.\n\n")
            # Group by birth year
            by_year = defaultdict(list)
            for id10 in pedi_lambs_not_in_lamb:
                yr = pedi_reg[id10].get("birth_year", "????")
                by_year[yr].append(id10)
            f.write(f"| Year | Count | Sample IDs |\n")
            f.write(f"|------|------:|------------|\n")
            for yr in sorted(by_year.keys()):
                ids = by_year[yr]
                sample = ", ".join(f"`{i}`" for i in ids[:5])
                if len(ids) > 5:
                    sample += f" ... +{len(ids)-5} more"
                f.write(f"| {yr} | {len(ids)} | {sample} |\n")
            f.write("\n")

        # Discrepancy detail sections
        disc_by_type = defaultdict(list)
        for d in discrepancies:
            disc_by_type[d["type"]].append(d)

        for dtype in sorted(disc_by_type.keys()):
            items = disc_by_type[dtype]
            f.write(f"## {dtype} ({len(items)})\n\n")
            # Show first 50
            for item in items[:50]:
                f.write(f"- **{item.get('file', '')}** "
                        f"row {item.get('row', '?')}: {item['detail']}\n")
            if len(items) > 50:
                f.write(f"\n*... and {len(items) - 50} more*\n")
            f.write("\n")

    print(f"  Report: {report_path}")


# ---------------------------------------------------------------------------
# Main driver
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="Cross-reference LAMB tables with PEDI registry",
    )
    parser.add_argument(
        "--pedi-dir",
        required=True,
        help="Path to pedigree-csv directory containing registry/ subfolder",
    )
    parser.add_argument(
        "--lamb-dir",
        required=True,
        help="Path to directory containing LAMB*.csv files",
    )
    parser.add_argument(
        "--outdir",
        help="Output directory (default: pedi-dir/registry/)",
    )
    args = parser.parse_args()

    pedi_dir = Path(args.pedi_dir)
    lamb_dir = Path(args.lamb_dir)
    out_dir = Path(args.outdir) if args.outdir else pedi_dir / "registry"

    registry_csv = pedi_dir / "registry" / "pedi_registry.csv"
    relationships_csv = pedi_dir / "registry" / "pedi_relationships.csv"

    if not registry_csv.exists():
        print(f"ERROR: pedi_registry.csv not found at {registry_csv}")
        sys.exit(1)
    if not relationships_csv.exists():
        print(f"ERROR: pedi_relationships.csv not found at {relationships_csv}")
        sys.exit(1)

    lamb_files = find_lamb_files(lamb_dir)
    if not lamb_files:
        print(f"ERROR: No LAMB CSV files found in {lamb_dir}")
        sys.exit(1)

    print(f"\n{'='*60}")
    print(f"PEDI ↔ LAMB Registry Builder")
    print(f"{'='*60}")
    print(f"PEDI registry: {registry_csv}")
    print(f"PEDI relationships: {relationships_csv}")
    print(f"LAMB files: {len(lamb_files)}")
    print(f"Output: {out_dir}")
    print(f"{'='*60}\n")

    # Load PEDI data
    print("Loading PEDI registry...")
    pedi_reg = load_pedi_registry(str(registry_csv))
    print(f"  {len(pedi_reg)} animals loaded")

    print("Loading PEDI relationships...")
    pedi_rels = load_pedi_relationships(str(relationships_csv))
    print(f"  {len(pedi_rels)} relationships loaded")

    # Process LAMB files
    output_rows = []
    seen_d_tags = set()
    discrepancies = []
    stats = defaultdict(int)

    for lamb_file in lamb_files:
        file_year = extract_year_from_filename(lamb_file.name)
        if file_year is None:
            print(f"  SKIP: Cannot extract year from {lamb_file.name}")
            continue

        print(f"Processing: {lamb_file.name} (year={file_year})")
        before_count = len(output_rows)

        process_lamb_file(
            lamb_file, file_year, pedi_reg, pedi_rels,
            output_rows, seen_d_tags, discrepancies, stats,
        )

        added = len(output_rows) - before_count
        print(f"  → {added} rows added")

    # Track which PEDI lamb IDs were matched
    matched_ids = set()
    for row in output_rows:
        if row["is_deceased_no_etag"] == "0":
            matched_ids.add(row["id10"])

    # Write output CSV
    os.makedirs(str(out_dir), exist_ok=True)
    out_csv = out_dir / "pedi_lamb_registry.csv"

    with open(out_csv, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=OUTPUT_FIELDS)
        writer.writeheader()
        for row in output_rows:
            writer.writerow(row)

    print(f"\n  Output: {out_csv} ({len(output_rows)} rows)")

    # Write report
    report_path = out_dir / "pedi_lamb_report.md"
    write_report(str(report_path), stats, discrepancies, pedi_reg, matched_ids)

    # Summary
    print(f"\n{'='*60}")
    print(f"SUMMARY")
    print(f"{'='*60}")
    print(f"Total LAMB rows processed: {stats['total_rows']}")
    print(f"  Living (no DISP/DAYDIS): {stats['living']}")
    print(f"  Deceased (has DISP or DAYDIS): {stats['deceased']}")
    print(f"  Matched to PEDI registry: {stats['matched_pedi']}")
    print(f"  D-tags generated: {stats['d_tags_generated']}")
    print(f"  D-tag failures: {stats['d_tag_failures']}")
    print(f"Output rows written: {len(output_rows)}")
    print()
    print(f"Discrepancies:")
    disc_by_type = defaultdict(int)
    for d in discrepancies:
        disc_by_type[d["type"]] += 1
    for dtype, count in sorted(disc_by_type.items()):
        print(f"  {dtype}: {count}")
    print()

    # PEDI coverage
    pedi_lamb_count = sum(1 for v in pedi_reg.values() if v.get("id_type") == "lamb")
    print(f"PEDI registry lambs (id_type=lamb): {pedi_lamb_count}")
    print(f"PEDI lambs matched in LAMB tables: {len(matched_ids)}")
    unmatched = pedi_lamb_count - len(matched_ids)
    print(f"PEDI lambs NOT in LAMB tables: {unmatched}")
    print(f"{'='*60}\n")


if __name__ == "__main__":
    main()
