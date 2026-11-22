import os
import re
import numpy as np
import pandas as pd
from sqlalchemy import create_engine

# =========================================================
# CONFIG
# =========================================================

DATA_DIR = r"c:\xampp\htdocs\PFE - Copy\prédiction\data"
os.makedirs(DATA_DIR, exist_ok=True)

engine = create_engine("mysql+pymysql://root:@localhost:3306/dw_labo")

# =========================================================
# EXTRACTION DATASET TRAIN
# =========================================================
# Objectif ML :
# prédire target_delai_jours = délai total depuis la date de demande
# jusqu'à la meilleure fin disponible.
#
# Priorité de la cible :
# 1) Livraison rapport : fait_rapport.delai_total_demande_livraison_jours
# 2) Validation rapport : DATEDIFF(date_validation_rapport, date_demande)
# 3) Validation / fin demande : fait_demande_analyse.delai_total_jours
#
# Cette version est adaptée à ta DW actuelle.
# =========================================================

query = """
WITH demande_client_agg AS (
    SELECT
        fdc.id_demande_analyse_sk,
        COUNT(*) AS nb_demande_client_liees,
        GROUP_CONCAT(DISTINCT ddc.ref_demande_client SEPARATOR ' | ') AS ref_demande_client,

        CASE
            WHEN SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(ddc.faisabilite, ''))) IN (
                        'non', 'non faisable', 'non-faisable', 'infaisable', 'no', 'false', '0'
                    )
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Non'

            WHEN SUM(
                CASE
                    WHEN ddc.faisabilite IS NULL
                      OR TRIM(ddc.faisabilite) = ''
                      OR LOWER(TRIM(ddc.faisabilite)) IN ('oui', 'faisable', 'yes', 'true', '1')
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Oui'

            ELSE NULL
        END AS faisabilite,

        CASE
            WHEN SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(ddc.validite, ''))) IN (
                        'non', 'non valide', 'non-valide', 'invalide', 'no', 'false', '0'
                    )
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Non'

            WHEN SUM(
                CASE
                    WHEN ddc.validite IS NULL
                      OR TRIM(ddc.validite) = ''
                      OR LOWER(TRIM(ddc.validite)) IN ('oui', 'valide', 'yes', 'true', '1')
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Oui'

            ELSE NULL
        END AS validite,

        MAX(CASE WHEN ddc.faisabilite IS NULL OR TRIM(ddc.faisabilite) = '' THEN 1 ELSE 0 END) AS faisabilite_null_flag,
        MAX(CASE WHEN ddc.validite IS NULL OR TRIM(ddc.validite) = '' THEN 1 ELSE 0 END) AS validite_null_flag

    FROM fait_demande_client fdc
    LEFT JOIN dim_demande_client ddc
        ON ddc.id_demande_client_sk = fdc.id_demande_client_sk
    WHERE fdc.id_demande_analyse_sk IS NOT NULL
    GROUP BY fdc.id_demande_analyse_sk
),

ech_agg AS (
    SELECT
        foa.id_demande_analyse_sk,

        GROUP_CONCAT(DISTINCT de.type_echantillon SEPARATOR ' | ') AS type_echantillon,
        GROUP_CONCAT(DISTINCT de.nom_produit SEPARATOR ' | ') AS nom_produit,
        GROUP_CONCAT(DISTINCT de.code_produit_demandeur SEPARATOR ' | ') AS code_produit_demandeur,
        GROUP_CONCAT(DISTINCT de.ref_labo SEPARATOR ' | ') AS ref_labo,
        GROUP_CONCAT(DISTINCT de.est_conforme SEPARATOR ' | ') AS est_conforme,

        CASE
            WHEN SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(de.est_conforme, ''))) IN (
                        'non', 'non conforme', 'non-conforme', 'nonconforme', 'nc', 'ko', '0', 'false'
                    )
                    OR LOWER(TRIM(COALESCE(de.est_conforme, ''))) LIKE '%%non%%conform%%'
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Non'

            WHEN SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(de.est_conforme, ''))) IN (
                        'oui', 'conforme', 'c', 'ok', '1', 'true'
                    )
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Oui'

            ELSE NULL
        END AS est_conforme_decision,

        MIN(de.date_reception) AS date_reception_echantillon,

        CASE
            WHEN MIN(de.ref_labo) IS NULL OR TRIM(MIN(de.ref_labo)) = '' THEN NULL
            WHEN LOCATE('*', MIN(de.ref_labo)) > 0 THEN TRIM(SUBSTRING_INDEX(MIN(de.ref_labo), '*', -1))
            ELSE TRIM(MIN(de.ref_labo))
        END AS code_ref_labo

    FROM fait_ordre_analyse foa
    LEFT JOIN dim_echantillon de
        ON de.id_echantillon_sk = foa.id_echantillon_sk
    WHERE foa.id_demande_analyse_sk IS NOT NULL
    GROUP BY foa.id_demande_analyse_sk
),

rapport_agg AS (
    SELECT
        fr.id_demande_analyse_sk,

        GROUP_CONCAT(DISTINCT fr.reference_rapport SEPARATOR ' | ') AS reference_rapport,

        MAX(
            CASE
                WHEN fr.delai_total_demande_livraison_jours IS NOT NULL
                 AND fr.delai_total_demande_livraison_jours >= 0
                THEN fr.delai_total_demande_livraison_jours
                ELSE NULL
            END
        ) AS delai_livraison_rapport_jours,

        MAX(
            CASE
                WHEN fr.delai_creation_validation_jours IS NOT NULL
                 AND fr.delai_creation_validation_jours >= 0
                THEN fr.delai_creation_validation_jours
                ELSE NULL
            END
        ) AS delai_creation_validation_jours,

        MAX(dtv.date_complete) AS date_validation_rapport,

        MAX(fr.rapport_final_flag) AS rapport_final_flag,
        MAX(fr.rapport_provisoire_flag) AS rapport_provisoire_flag,
        COUNT(*) AS nb_rapports

    FROM fait_rapport fr
    LEFT JOIN dim_temps dtv
        ON dtv.id_temps = fr.id_temps_validation
    WHERE fr.id_demande_analyse_sk IS NOT NULL
    GROUP BY fr.id_demande_analyse_sk
),

resultat_agg AS (
    SELECT
        fra.id_demande_analyse_sk,
        GROUP_CONCAT(DISTINCT fra.nom_resultat SEPARATOR ' | ') AS nom_resultat,
        COUNT(*) AS nb_lignes_resultats,
        AVG(fra.resultat_num) AS moyenne_resultat_num,
        MAX(fra.is_accredited) AS is_accredited_flag
    FROM fait_resultat_analyse fra
    WHERE fra.id_demande_analyse_sk IS NOT NULL
    GROUP BY fra.id_demande_analyse_sk
),

analyse_agg AS (
    SELECT
        foa.id_demande_analyse_sk,

        COUNT(DISTINCT da.id_analyse_bk) AS nb_analyses_distinct,

        GROUP_CONCAT(
            DISTINCT COALESCE(
                NULLIF(TRIM(da.code_analyse), ''),
                NULLIF(TRIM(da.nom_analyse), ''),
                CAST(da.id_analyse_bk AS CHAR)
            )
            ORDER BY COALESCE(
                NULLIF(TRIM(da.code_analyse), ''),
                NULLIF(TRIM(da.nom_analyse), ''),
                CAST(da.id_analyse_bk AS CHAR)
            )
            SEPARATOR '||'
        ) AS analyse_list

    FROM fait_ordre_analyse foa
    LEFT JOIN dim_analyse da
        ON da.id_analyse_sk = foa.id_analyse_sk
    WHERE foa.id_demande_analyse_sk IS NOT NULL
      AND da.id_analyse_bk IS NOT NULL
      AND da.id_analyse_bk <> 0
    GROUP BY foa.id_demande_analyse_sk
),

laborantin_agg AS (
    SELECT
        foa.id_demande_analyse_sk,

        COUNT(DISTINCT dl.id_laborantin_bk) AS nb_laborantins_distinct,

        GROUP_CONCAT(
            DISTINCT CAST(dl.id_laborantin_bk AS CHAR)
            ORDER BY CAST(dl.id_laborantin_bk AS CHAR)
            SEPARATOR '||'
        ) AS laborantin_list

    FROM fait_ordre_analyse foa
    LEFT JOIN dim_laborantin dl
        ON dl.id_laborantin_sk = foa.id_laborantin_sk
    WHERE foa.id_demande_analyse_sk IS NOT NULL
      AND dl.id_laborantin_bk IS NOT NULL
      AND dl.id_laborantin_bk <> 0
    GROUP BY foa.id_demande_analyse_sk
)

SELECT
    dda.id_demande_analyse_bk AS id_demande,
    fda.id_demande_analyse_sk,
    dda.ref_demande AS reference_demande,

    fda.id_client_sk,
    dc.id_client_bk AS id_client,
    dc.client_name AS client_nom,
    dc.type_client,

    fda.id_secteur_sk,
    ds.id_secteur_bk AS id_secteur,
    ds.nom_secteur AS secteur_nom,

    fda.id_statut_sk,
    dstat.statut_demande,
    dstat.statut_ordre,
    dstat.statut_rapport,
    dstat.statut_prediction,

    dt_dem.date_complete AS date_demande,

    COALESCE(dca.faisabilite, 'Oui') AS faisabilite,
    COALESCE(dca.validite, 'Oui') AS validite,

    COALESCE(dca.nb_demande_client_liees, 0) AS nb_demande_client_liees,
    COALESCE(dca.faisabilite_null_flag, 0) AS faisabilite_null_flag,
    COALESCE(dca.validite_null_flag, 0) AS validite_null_flag,

    COALESCE(ech.est_conforme_decision, dstat.conformite) AS est_conforme,

    fda.nb_demandes_analyse,
    fda.nb_analyses,
    fda.nb_ordres,
    fda.nb_resultats,
    fda.nb_echantillons AS nb_fiches_paillasse,

    ana.analyse_list,
    COALESCE(ana.nb_analyses_distinct, 0) AS nb_analyses_distinct,

    lab.laborantin_list,
    COALESCE(lab.nb_laborantins_distinct, 0) AS nb_laborantins_distinct,

    fda.delai_reception_jours AS delai_reception,
    fda.delai_affectation_jours AS delai_affectation,
    fda.delai_debut_analyse_jours AS delai_debut_analyse,
    fda.delai_total_jours AS delai_total_demande,

    ech.type_echantillon,
    ech.nom_produit,
    ech.code_produit_demandeur,
    ech.ref_labo,
    ech.code_ref_labo,
    ech.date_reception_echantillon,

    dca.ref_demande_client,

    rapp.reference_rapport,
    COALESCE(rapp.nb_rapports, 0) AS nb_rapports,

    rapp.delai_livraison_rapport_jours,
    rapp.date_validation_rapport,

    CASE
        WHEN rapp.date_validation_rapport IS NOT NULL
         AND DATEDIFF(rapp.date_validation_rapport, dt_dem.date_complete) >= 0
        THEN DATEDIFF(rapp.date_validation_rapport, dt_dem.date_complete)
        ELSE NULL
    END AS delai_validation_rapport_jours,

    CASE
        WHEN fda.delai_total_jours IS NOT NULL
         AND fda.delai_total_jours >= 0
        THEN fda.delai_total_jours
        ELSE NULL
    END AS delai_validation_demande_jours,

    COALESCE(
        rapp.delai_livraison_rapport_jours,
        CASE
            WHEN rapp.date_validation_rapport IS NOT NULL
             AND DATEDIFF(rapp.date_validation_rapport, dt_dem.date_complete) >= 0
            THEN DATEDIFF(rapp.date_validation_rapport, dt_dem.date_complete)
            ELSE NULL
        END,
        CASE
            WHEN fda.delai_total_jours IS NOT NULL
             AND fda.delai_total_jours >= 0
            THEN fda.delai_total_jours
            ELSE NULL
        END
    ) AS target_delai_jours,

    CASE
        WHEN rapp.delai_livraison_rapport_jours IS NOT NULL
        THEN 'Livraison rapport'

        WHEN rapp.date_validation_rapport IS NOT NULL
         AND DATEDIFF(rapp.date_validation_rapport, dt_dem.date_complete) >= 0
        THEN 'Validation rapport'

        WHEN fda.delai_total_jours IS NOT NULL
         AND fda.delai_total_jours >= 0
        THEN 'Validation demande'

        ELSE 'Aucune cible'
    END AS target_source,

    rapp.delai_creation_validation_jours,

    CASE
        WHEN rapp.rapport_final_flag = 1 AND rapp.rapport_provisoire_flag = 1 THEN 'Mixte'
        WHEN rapp.rapport_final_flag = 1 THEN 'Final'
        WHEN rapp.rapport_provisoire_flag = 1 THEN 'Provisoire'
        ELSE NULL
    END AS type_rapport,

    res.nom_resultat,
    res.nb_lignes_resultats,
    res.moyenne_resultat_num,
    res.is_accredited_flag

FROM fait_demande_analyse fda

LEFT JOIN dim_demande_analyse dda
    ON dda.id_demande_analyse_sk = fda.id_demande_analyse_sk

LEFT JOIN dim_client dc
    ON dc.id_client_sk = fda.id_client_sk

LEFT JOIN dim_secteur ds
    ON ds.id_secteur_sk = fda.id_secteur_sk

LEFT JOIN dim_statut dstat
    ON dstat.id_statut_sk = fda.id_statut_sk

LEFT JOIN dim_temps dt_dem
    ON dt_dem.id_temps = fda.id_temps_demande

LEFT JOIN demande_client_agg dca
    ON dca.id_demande_analyse_sk = fda.id_demande_analyse_sk

LEFT JOIN ech_agg ech
    ON ech.id_demande_analyse_sk = fda.id_demande_analyse_sk

LEFT JOIN rapport_agg rapp
    ON rapp.id_demande_analyse_sk = fda.id_demande_analyse_sk

LEFT JOIN resultat_agg res
    ON res.id_demande_analyse_sk = fda.id_demande_analyse_sk

LEFT JOIN analyse_agg ana
    ON ana.id_demande_analyse_sk = fda.id_demande_analyse_sk

LEFT JOIN laborantin_agg lab
    ON lab.id_demande_analyse_sk = fda.id_demande_analyse_sk

WHERE dt_dem.date_complete IS NOT NULL
  AND dda.id_demande_analyse_bk IS NOT NULL

  AND COALESCE(
        rapp.delai_livraison_rapport_jours,
        CASE
            WHEN rapp.date_validation_rapport IS NOT NULL
             AND DATEDIFF(rapp.date_validation_rapport, dt_dem.date_complete) >= 0
            THEN DATEDIFF(rapp.date_validation_rapport, dt_dem.date_complete)
            ELSE NULL
        END,
        CASE
            WHEN fda.delai_total_jours IS NOT NULL
             AND fda.delai_total_jours >= 0
            THEN fda.delai_total_jours
            ELSE NULL
        END
      ) IS NOT NULL

  AND dc.id_client_bk IS NOT NULL
  AND dc.id_client_bk <> 0

  AND (
        COALESCE(ech.est_conforme_decision, dstat.conformite) IS NULL
        OR LOWER(TRIM(COALESCE(ech.est_conforme_decision, dstat.conformite))) NOT IN (
            'non', 'non conforme', 'non-conforme', 'nonconforme', 'nc', 'ko', '0', 'false'
        )
      )

  AND COALESCE(dca.faisabilite, 'Oui') <> 'Non'
  AND COALESCE(dca.validite, 'Oui') <> 'Non'
"""

df = pd.read_sql(query, engine)

# =========================================================
# ML CLEANING FUNCTIONS
# =========================================================

UNKNOWN_TOKENS = {
    "Inconnu", "inconnu", "INCONNU",
    "Unknown", "unknown",
    "", " ",
    "NULL", "None", "none",
    "nan", "NaN", "<NA>",
}


def normalize_text(series: pd.Series) -> pd.Series:
    s = series.copy()
    s = (
        s.astype("string")
         .str.strip()
         .str.lower()
         .str.replace("é", "e", regex=False)
         .str.replace("è", "e", regex=False)
         .str.replace("ê", "e", regex=False)
         .str.replace("à", "a", regex=False)
         .str.replace("_", " ", regex=False)
         .str.replace("-", " ", regex=False)
    )
    s = s.str.replace(r"\s+", " ", regex=True)
    return s


def normalize_unknowns(series: pd.Series) -> pd.Series:
    s = series.copy()
    if s.dtype == "object" or pd.api.types.is_string_dtype(s):
        s = s.astype("string").str.strip()
        s = s.replace(list(UNKNOWN_TOKENS), np.nan)
    return s.where(pd.notna(s), np.nan)


def clean_category_exact(series: pd.Series) -> pd.Series:
    s = normalize_unknowns(series)
    if s.dtype == "object" or pd.api.types.is_string_dtype(s):
        s = s.astype("string").str.strip()
        s = s.replace(list(UNKNOWN_TOKENS), np.nan)
    return s.astype("object").where(pd.notna(s), np.nan)


def split_multivalue_tokens(value) -> list[str]:
    if pd.isna(value):
        return []

    raw = str(value).strip()
    if raw in UNKNOWN_TOKENS:
        return []

    tokens = []
    for token in raw.split("||"):
        token = str(token).strip()
        if token and token not in UNKNOWN_TOKENS:
            tokens.append(token)

    return sorted(set(tokens))


def safe_feature_suffix(value: str) -> str:
    value = str(value).strip()
    value = re.sub(r"[^0-9A-Za-z_]+", "_", value)
    value = re.sub(r"_+", "_", value).strip("_")

    if not value:
        value = "UNKNOWN"

    return value[:80]


def add_multihot_columns(
    df: pd.DataFrame,
    source_col: str,
    prefix: str,
) -> tuple[pd.DataFrame, list[str]]:
    if source_col not in df.columns:
        return df, []

    token_lists = df[source_col].apply(split_multivalue_tokens)
    all_tokens = sorted({token for tokens in token_lists for token in tokens})

    created_cols = []
    used_names = set(df.columns)

    for token in all_tokens:
        suffix = safe_feature_suffix(token)
        col = f"{prefix}{suffix}"

        if col in used_names:
            i = 2
            base_col = col
            while f"{base_col}_{i}" in used_names:
                i += 1
            col = f"{base_col}_{i}"

        df[col] = token_lists.apply(lambda tokens, t=token: 1 if t in tokens else 0).astype(int)
        created_cols.append(col)
        used_names.add(col)

    return df, created_cols


def yes_no_clean(series: pd.Series) -> pd.Series:
    s = normalize_unknowns(series)
    norm = normalize_text(s)

    out = pd.Series(np.nan, index=series.index, dtype="object")

    non_pattern = (
        r"(non conforme|nonconforme|\bnc\b|\bko\b|"
        r"\bnon\b|invalide|non valide|"
        r"non faisable|infaisable|non realisable)"
    )

    yes_pattern = (
        r"(\boui\b|\byes\b|\btrue\b|\b1\b|"
        r"\bconforme\b|\bc\b|\bok\b|"
        r"\bvalide\b|faisable|realisable)"
    )

    non_mask = norm.str.contains(non_pattern, regex=True, na=False)
    yes_mask = norm.str.contains(yes_pattern, regex=True, na=False)

    out.loc[non_mask] = "Non"
    out.loc[yes_mask & ~non_mask] = "Oui"

    return out.astype("object")


# =========================================================
# BASIC CLEANING
# =========================================================

text_defaults = {
    "client_nom": "Inconnu",
    "type_client": "Inconnu",
    "secteur_nom": "Inconnu",
    "statut_demande": "Inconnu",
    "statut_ordre": "Inconnu",
    "statut_rapport": "Inconnu",
    "statut_prediction": "Inconnu",
    "type_echantillon": "Inconnu",
    "nom_produit": "Inconnu",
    "code_produit_demandeur": "Inconnu",
    "ref_labo": "Inconnu",
    "code_ref_labo": "Inconnu",
    "ref_demande_client": "Inconnu",
    "reference_rapport": "Inconnu",
    "type_rapport": "Inconnu",
    "target_source": "Aucune cible",
    "nom_resultat": "Inconnu",
    "reference_demande": "Inconnu",
}

for col, default in text_defaults.items():
    if col in df.columns:
        df[col] = df[col].fillna(default)

numeric_defaults = {
    "nb_demandes_analyse": 1,
    "nb_analyses": 0,
    "nb_ordres": 0,
    "nb_resultats": 0,
    "nb_fiches_paillasse": 0,
    "nb_rapports": 0,
    "nb_lignes_resultats": 0,
    "is_accredited_flag": 0,
    "nb_demande_client_liees": 0,
    "faisabilite_null_flag": 0,
    "validite_null_flag": 0,
    "nb_analyses_distinct": 0,
    "nb_laborantins_distinct": 0,
    "delai_livraison_rapport_jours": np.nan,
    "delai_validation_rapport_jours": np.nan,
    "delai_validation_demande_jours": np.nan,
}

for col, default in numeric_defaults.items():
    if col in df.columns:
        df[col] = df[col].fillna(default)

df["date_demande"] = pd.to_datetime(df["date_demande"], errors="coerce")

if "date_reception_echantillon" in df.columns:
    df["date_reception_echantillon"] = pd.to_datetime(df["date_reception_echantillon"], errors="coerce")

if "date_validation_rapport" in df.columns:
    df["date_validation_rapport"] = pd.to_datetime(df["date_validation_rapport"], errors="coerce")

# =========================================================
# BUSINESS ML VARIABLES - EXACT CATEGORIES
# =========================================================

client_raw = df["id_client"].astype("string")
client_raw = client_raw.replace(["0", "0.0", "<NA>", "nan", "None"], np.nan)

df["client_ml"] = clean_category_exact(client_raw)
df["secteur_ml"] = clean_category_exact(df["secteur_nom"])
df["faisabilite_ml"] = yes_no_clean(df["faisabilite"])
df["validite_ml"] = yes_no_clean(df["validite"])
df["est_conforme_ml"] = yes_no_clean(df["est_conforme"])
df["type_echantillon_ml"] = clean_category_exact(df["type_echantillon"])
df["nom_produit_ml"] = clean_category_exact(df["nom_produit"])
df["code_ref_labo_ml"] = clean_category_exact(df["code_ref_labo"])

df, analyse_feature_cols = add_multihot_columns(df, "analyse_list", "analyse__")
df, laborantin_feature_cols = add_multihot_columns(df, "laborantin_list", "laborantin__")

if "nb_analyses_distinct" in df.columns:
    df["nb_analyses_distinct"] = df["nb_analyses_distinct"].fillna(0).astype(int)
else:
    df["nb_analyses_distinct"] = 0

if "nb_laborantins_distinct" in df.columns:
    df["nb_laborantins_distinct"] = df["nb_laborantins_distinct"].fillna(0).astype(int)
else:
    df["nb_laborantins_distinct"] = 0

print("\nMulti-hot analyse columns:", len(analyse_feature_cols))
print("Multi-hot laborantin columns:", len(laborantin_feature_cols))

df["client_connu_flag"] = (df["id_client"].notna() & (df["id_client"] != 0)).astype(int)
df["secteur_connu_flag"] = (df["id_secteur"].notna() & (df["id_secteur"] != 0)).astype(int)
df["produit_connu_flag"] = normalize_unknowns(df["nom_produit"]).notna().astype(int)
df["ref_labo_connu_flag"] = normalize_unknowns(df["code_ref_labo"]).notna().astype(int)
df["echantillon_connu_flag"] = normalize_unknowns(df["type_echantillon"]).notna().astype(int)

print("\n=== Verification variables metier ML ===")
for col in [
    "id_client",
    "client_ml",
    "secteur_ml",
    "faisabilite",
    "faisabilite_ml",
    "validite",
    "validite_ml",
    "est_conforme",
    "est_conforme_ml",
    "nb_demande_client_liees",
    "target_source",
    "target_delai_jours",
    "analyse_list",
    "laborantin_list",
    "nb_analyses_distinct",
    "nb_laborantins_distinct",
]:
    if col in df.columns:
        print(f"\n{col}")
        print(df[col].value_counts(dropna=False).head(20))

df_train = df[
    df["date_demande"].notna()
    & df["target_delai_jours"].notna()
    & (df["target_delai_jours"] >= 0)
    & df["client_ml"].notna()
    & (df["est_conforme_ml"] != "Non")
    & (df["faisabilite_ml"] != "Non")
    & (df["validite_ml"] != "Non")
].copy()

print("\n=== Repartition des sources de target ===")
if "target_source" in df_train.columns:
    print(df_train["target_source"].value_counts(dropna=False))

print("\n=== Statistiques target_delai_jours ===")
print(df_train["target_delai_jours"].describe())

# =========================================================
# DERIVED VARIABLES
# =========================================================

df_train["mois_demande"] = df_train["date_demande"].dt.month
df_train["trimestre_demande"] = df_train["date_demande"].dt.quarter
df_train["jour_semaine_demande"] = df_train["date_demande"].dt.dayofweek

if "date_reception_echantillon" in df_train.columns:
    df_train["mois_reception_echantillon"] = df_train["date_reception_echantillon"].dt.month
    df_train["jour_semaine_reception_echantillon"] = df_train["date_reception_echantillon"].dt.dayofweek

required_optional_cols = [
    "delai_affectation",
    "delai_debut_analyse",
    "delai_reception",
    "delai_total_demande",
    "delai_creation_validation_jours",
    "delai_livraison_rapport_jours",
    "delai_validation_rapport_jours",
    "delai_validation_demande_jours",
    "target_source",
]

for col in required_optional_cols:
    if col not in df_train.columns:
        df_train[col] = None

# =========================================================
# SAVE
# =========================================================

output_path = os.path.join(DATA_DIR, "dataset_train.pkl")
df_train.to_pickle(output_path)

print("\nColonnes disponibles :")
print(df_train.columns.tolist())

print()
print("Dataset brut filtre SQL :", df.shape)
print("Dataset entrainable final :", df_train.shape)
print()

preview_cols = [
    "id_demande",
    "id_demande_analyse_sk",
    "id_client",
    "client_ml",
    "secteur_ml",
    "faisabilite",
    "faisabilite_ml",
    "validite",
    "validite_ml",
    "est_conforme",
    "est_conforme_ml",
    "nb_demande_client_liees",
    "type_echantillon_ml",
    "nom_produit_ml",
    "code_ref_labo_ml",
    "nb_analyses",
    "analyse_list",
    "laborantin_list",
    "nb_analyses_distinct",
    "nb_laborantins_distinct",
    "target_source",
    "delai_livraison_rapport_jours",
    "delai_validation_rapport_jours",
    "delai_validation_demande_jours",
    "trimestre_demande",
    "date_demande",
    "target_delai_jours",
]
preview_cols = [c for c in preview_cols if c in df_train.columns]
print(df_train[preview_cols].head())
print()
print("Fichier sauvegarde :", output_path)



