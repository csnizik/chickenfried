# Google Sheets Formula Reference

## INDEX/MATCH (use instead of VLOOKUP)

INDEX/MATCH only touches the lookup column and the return column. No column-counting, no range size limits, works regardless of column distance.

### Cross-document
```
=IFERROR(INDEX(IMPORTRANGE("1jcDRC-12sbUP_Mpt0lXkV-rkO1vbzZj9E3Z_8JypRXE","LAMB-MASTER-10-12!BG4:BG"),MATCH(TRIM($A4),TRIM(IMPORTRANGE("1jcDRC-12sbUP_Mpt0lXkV-rkO1vbzZj9E3Z_8JypRXE","LAMB-MASTER-10-12!B4:B")),0)),"")
```

### Same document, different sheet
```
=IFERROR(INDEX('LAMB-MASTER-10-12'!BG:BG,MATCH(TRIM($A4),TRIM('LAMB-MASTER-10-12'!B:B),0)),"")
```

### Same document, same sheet
```
=IFERROR(INDEX(BG:BG,MATCH(TRIM($A4),TRIM(B:B),0)),"")
```

### Data type mismatch (N/A errors)
If values look identical but MATCH returns N/A, force both sides to the same type.
Text-to-text:
```
=INDEX('SheetName'!BG:BG,MATCH(TEXT($A2,"0"),'SheetName'!B:B,0))
```
Text-to-number:
```
=INDEX('SheetName'!BG:BG,MATCH(VALUE($A2),'SheetName'!B:B,0))
```

---

## Unique Values Not Already in Destination

### Same document, different sheet
All unique values in SourceSheet column A that do not appear in current sheet column A.
```
=UNIQUE(FILTER('SourceSheet'!A:A, ISNA(MATCH('SourceSheet'!A:A, A:A, 0)), 'SourceSheet'!A:A<>""))
```

### Cross-document
```
=UNIQUE(FILTER(IMPORTRANGE("DOCUMENT_ID","SheetName!A2:A"), ISNA(MATCH(IMPORTRANGE("DOCUMENT_ID","SheetName!A2:A"), A:A, 0))))
```

---

## Unique Values in a Column

### Same sheet
All unique non-empty values in column A.
```
=UNIQUE(FILTER(A:A, A:A<>""))
```

---

## Count / Validate

### Count unique values in a column
```
=COUNTA(UNIQUE(FILTER(A:A, A:A<>"")))
```

### Count non-empty cells
```
=COUNTA(A2:A)
```

### Count rows where column A is not empty but column E is empty
Useful for finding rows missing a value that should have been populated.
```
=COUNTIFS(A2:A,"<>",E2:E,"")
```

### Count rows matching a specific value
```
=COUNTIF(A:A,"some_value")
```

---

## Conditional Extraction

### Extract first N characters and test
If first 4 chars of A are a year between 1990-2025, return characters 5-6; if first char is "U", return characters 3-4; else empty.
```
=IF(ISNUMBER(VALUE(LEFT($A2,4))),IF(AND(VALUE(LEFT($A2,4))>=1990,VALUE(LEFT($A2,4))<=2025),VALUE(MID($A2,5,2)),""),IF(LEFT($A2,1)="U",VALUE(MID($A2,3,2)),""))
```

---

## IMPORTRANGE Tips

- First use of IMPORTRANGE to a new document triggers a one-time "Allow access" prompt.
- Open-ended ranges like `A3:A` truncate trailing empty rows. If you need every column to have exactly the same row count, either close the range to a fixed row number (`A3:A5000`) or import multi-column blocks so all columns share the same rectangle.
- IMPORTRANGE pulls displayed values, not formulas. Formula-derived cells work fine.
