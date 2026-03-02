# PEDI ↔ LAMB Cross-Reference Report

## Summary

| Metric | Count |
|--------|------:|
| d_tags_generated | 56794 |
| deceased | 298171 |
| living | 47705 |
| living_not_in_pedi | 189 |
| matched_pedi | 277095 |
| no_tag_not_deceased | 3365 |
| parse_errors | 2 |
| total_rows | 345878 |

## PEDI Registry Lambs Not Found in LAMB Tables (21904)

These animals exist in pedi_registry as `id_type=lamb` but were not matched to any LAMB CSV row.

| Year | Count | Sample IDs |
|------|------:|------------|
| 1966 | 1 | `1966053392` |
| 1967 | 39 | `1967102639`, `1967102641`, `1967102642`, `1967102644`, `1967102645` ... +34 more |
| 1968 | 235 | `1968102757`, `1968102758`, `1968102759`, `1968102760`, `1968102761` ... +230 more |
| 1969 | 532 | `1969103484`, `1969103497`, `1969103502`, `1969103503`, `1969103508` ... +527 more |
| 1970 | 823 | `1970100163`, `1970100164`, `1970100165`, `1970100166`, `1970100167` ... +818 more |
| 1971 | 761 | `1971010501`, `1971010502`, `1971010503`, `1971010504`, `1971010505` ... +756 more |
| 1972 | 720 | `1972001867`, `1972011263`, `1972011264`, `1972011265`, `1972011266` ... +715 more |
| 1973 | 770 | `1973011995`, `1973011996`, `1973011997`, `1973011998`, `1973011999` ... +765 more |
| 1974 | 870 | `1974012765`, `1974012766`, `1974012767`, `1974012768`, `1974012769` ... +865 more |
| 1975 | 934 | `1975010100`, `1975010200`, `1975010210`, `1975010310`, `1975010370` ... +929 more |
| 1976 | 793 | `1976014533`, `1976014534`, `1976014535`, `1976014536`, `1976014537` ... +788 more |
| 1977 | 639 | `1977015345`, `1977015346`, `1977015347`, `1977015348`, `1977015349` ... +634 more |
| 1978 | 890 | `1978016262`, `1978016263`, `1978016264`, `1978016265`, `1978016266` ... +885 more |
| 1979 | 1118 | `1979017529`, `1979017530`, `1979017531`, `1979017532`, `1979017533` ... +1113 more |
| 1980 | 978 | `1980019258`, `1980019259`, `1980019260`, `1980019261`, `1980019262` ... +973 more |
| 1981 | 1132 | `1981020568`, `1981020569`, `1981020570`, `1981020571`, `1981020572` ... +1127 more |
| 1982 | 1433 | `1982021999`, `1982022000`, `1982022001`, `1982022002`, `1982022003` ... +1428 more |
| 1983 | 1419 | `1983023875`, `1983023876`, `1983023877`, `1983023878`, `1983023879` ... +1414 more |
| 1984 | 468 | `1984027364`, `1984027365`, `1984027366`, `1984027367`, `1984027368` ... +463 more |
| 1985 | 651 | `1985028445`, `1985028446`, `1985028447`, `1985028448`, `1985028449` ... +646 more |
| 1986 | 1044 | `1986029750`, `1986029751`, `1986029752`, `1986029753`, `1986029754` ... +1039 more |
| 1987 | 877 | `1987031062`, `1987031063`, `1987031064`, `1987031065`, `1987031066` ... +872 more |
| 1988 | 850 | `1988032577`, `1988032578`, `1988032579`, `1988032580`, `1988032581` ... +845 more |
| 1989 | 384 | `1989034410`, `1989034411`, `1989034412`, `1989034413`, `1989034414` ... +379 more |
| 1990 | 968 | `1990035839`, `1990035840`, `1990035841`, `1990035842`, `1990035843` ... +963 more |
| 1991 | 708 | `1991000301`, `1991000302`, `1991037838`, `1991037839`, `1991037840` ... +703 more |
| 1992 | 377 | `1992039361`, `1992039362`, `1992039363`, `1992039364`, `1992039365` ... +372 more |
| 1993 | 480 | `1993040243`, `1993040244`, `1993040245`, `1993040246`, `1993040247` ... +475 more |
| 1994 | 242 | `1994041517`, `1994041518`, `1994041519`, `1994041520`, `1994041521` ... +237 more |
| 1995 | 215 | `1995042578`, `1995042579`, `1995042580`, `1995042581`, `1995042582` ... +210 more |
| 1996 | 311 | `1996000000`, `1996043664`, `1996043665`, `1996043666`, `1996043667` ... +306 more |
| 2002 | 1 | `2002040000` |
| 2003 | 238 | `2003003001`, `2003003002`, `2003003003`, `2003003004`, `2003003005` ... +233 more |
| 2010 | 2 | `2010829240`, `2010829523` |
| 2012 | 1 | `2012831317` |

## LAMB_ID_PARSE_ERROR (2)

- **LAMB1970.csv** row 3136: Cannot build ID10: LAMB='174x' LBRD='62' YR=1970
- **LAMB2010.csv** row 148: Cannot build ID10: LAMB='3' LBRD='' YR=2010

## LIVING_NOT_IN_PEDI (189)

- **LAMB1960.csv** row 4061: Living lamb id10=1960482410 not found in pedi_registry
- **LAMB1960.csv** row 4062: Living lamb id10=1960614383 not found in pedi_registry
- **LAMB1960.csv** row 4063: Living lamb id10=1960900134 not found in pedi_registry
- **LAMB1960.csv** row 4064: Living lamb id10=1960900143 not found in pedi_registry
- **LAMB1967.csv** row 5124: Living lamb id10=1967903983 not found in pedi_registry
- **LAMB1967.csv** row 5125: Living lamb id10=1967904080 not found in pedi_registry
- **LAMB1967.csv** row 5126: Living lamb id10=1967904116 not found in pedi_registry
- **LAMB1967.csv** row 5127: Living lamb id10=1967904281 not found in pedi_registry
- **LAMB1967.csv** row 5128: Living lamb id10=1967904294 not found in pedi_registry
- **LAMB1967.csv** row 5129: Living lamb id10=1967904295 not found in pedi_registry
- **LAMB1967.csv** row 5130: Living lamb id10=1967904320 not found in pedi_registry
- **LAMB1967.csv** row 5155: Living lamb id10=1967904549 not found in pedi_registry
- **LAMB1967.csv** row 5217: Living lamb id10=1967905419 not found in pedi_registry
- **LAMB1971.csv** row 4059: Living lamb id10=1971908079 not found in pedi_registry
- **LAMB1973.csv** row 4182: Living lamb id10=1973624511 not found in pedi_registry
- **LAMB1973.csv** row 4183: Living lamb id10=1973624512 not found in pedi_registry
- **LAMB1973.csv** row 4184: Living lamb id10=1973624883 not found in pedi_registry
- **LAMB1973.csv** row 4185: Living lamb id10=1973909180 not found in pedi_registry
- **LAMB1977.csv** row 6127: Living lamb id10=1977934739 not found in pedi_registry
- **LAMB2003.csv** row 39: Living lamb id10=2003818582 not found in pedi_registry
- **LAMB2003.csv** row 241: Living lamb id10=2003818748 not found in pedi_registry
- **LAMB2003.csv** row 242: Living lamb id10=2003818747 not found in pedi_registry
- **LAMB2003.csv** row 648: Living lamb id10=2003819041 not found in pedi_registry
- **LAMB2003.csv** row 1085: Living lamb id10=2003819378 not found in pedi_registry
- **LAMB2003.csv** row 1086: Living lamb id10=2003819379 not found in pedi_registry
- **LAMB2003.csv** row 2224: Living lamb id10=2003820120 not found in pedi_registry
- **LAMB2003.csv** row 2837: Living lamb id10=2003820485 not found in pedi_registry
- **LAMB2003.csv** row 2838: Living lamb id10=2003820484 not found in pedi_registry
- **LAMB2003.csv** row 2839: Living lamb id10=2003820483 not found in pedi_registry
- **LAMB2003.csv** row 2981: Living lamb id10=2003820600 not found in pedi_registry
- **LAMB2003.csv** row 3104: Living lamb id10=2003820618 not found in pedi_registry
- **LAMB2003.csv** row 3105: Living lamb id10=2003820619 not found in pedi_registry
- **LAMB2003.csv** row 3126: Living lamb id10=2003820639 not found in pedi_registry
- **LAMB2003.csv** row 3577: Living lamb id10=2003303238 not found in pedi_registry
- **LAMB2003.csv** row 4123: Living lamb id10=2003303001 not found in pedi_registry
- **LAMB2003.csv** row 4125: Living lamb id10=2003303007 not found in pedi_registry
- **LAMB2003.csv** row 4127: Living lamb id10=2003303004 not found in pedi_registry
- **LAMB2003.csv** row 4129: Living lamb id10=2003303005 not found in pedi_registry
- **LAMB2003.csv** row 4130: Living lamb id10=2003303006 not found in pedi_registry
- **LAMB2003.csv** row 4131: Living lamb id10=2003303009 not found in pedi_registry
- **LAMB2003.csv** row 4132: Living lamb id10=2003303010 not found in pedi_registry
- **LAMB2003.csv** row 4133: Living lamb id10=2003303012 not found in pedi_registry
- **LAMB2003.csv** row 4135: Living lamb id10=2003303017 not found in pedi_registry
- **LAMB2003.csv** row 4138: Living lamb id10=2003303021 not found in pedi_registry
- **LAMB2003.csv** row 4140: Living lamb id10=2003303019 not found in pedi_registry
- **LAMB2003.csv** row 4141: Living lamb id10=2003303016 not found in pedi_registry
- **LAMB2003.csv** row 4142: Living lamb id10=2003303015 not found in pedi_registry
- **LAMB2003.csv** row 4145: Living lamb id10=2003303023 not found in pedi_registry
- **LAMB2003.csv** row 4146: Living lamb id10=2003303024 not found in pedi_registry
- **LAMB2003.csv** row 4147: Living lamb id10=2003303030 not found in pedi_registry

*... and 139 more*

## NO_TAG_NOT_DECEASED (3365)

- **LAMB1978.csv** row 6268: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1978.csv** row 6269: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1978.csv** row 6270: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1979.csv** row 6294: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1979.csv** row 6295: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1979.csv** row 6296: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3978: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3979: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3980: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3981: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3982: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3983: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3984: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3985: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3986: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3987: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3988: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3989: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3990: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3991: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3992: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3993: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3994: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3995: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3996: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3997: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 3998: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4000: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4001: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4003: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4004: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4005: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4006: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4007: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4008: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4010: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4011: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4012: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4014: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4015: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4016: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4017: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4018: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4019: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4020: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4021: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4022: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4023: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4024: LAMB='' has no ear tag and no DISP/DAYDIS
- **LAMB1996.csv** row 4025: LAMB='' has no ear tag and no DISP/DAYDIS

*... and 3315 more*

