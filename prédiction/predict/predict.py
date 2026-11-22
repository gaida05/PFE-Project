import os
import sys
import re
import math
import joblib
import numpy as np
import pandas as pd
from sqlalchemy import create_engine
BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)
DB_URL = "mysql+pymysql://root:@localhost:3306/dw_labo"
DATA_DIR = os.path.join(BASE_DIR, "data")
OUTPUTS_DIR = os.path.join(BASE_DIR, "outputs")
os.makedirs(DATA_DIR, exist_ok=True)
STAGES = ["precoce", "intermediaire", "avancee"]
USE_DATA_REFERENCE_DATE = True
LOOKBACK_MONTHS = 2
RETARD_THRESHOLD_JOURS = 7.0
PREDICTIONS_OUTPUT_PATH = os.path.join(DATA_DIR, "predictions_en_cours.csv")
engine = create_engine(DB_URL)
def is_missing(value) -> bool:
    if value is None:
        return True
    try:
        if pd.isna(value):
            return True
    except Exception:
        pass
    if isinstance(value, str):
        return value.strip() in {"", "nan", "NaN", "None", "NULL", "<NA>", "Inconnu", "inconnu"}

    return False
def safe_float(value):
    if is_missing(value):
        return None
    try:
        x = float(value)
        if math.isnan(x) or math.isinf(x):
            return None
        return x
    except Exception:
        return None
def safe_int(value):
    if is_missing(value):
        return None
    try:
        x = float(value)
        if math.isnan(x) or math.isinf(x):
            return None
        return int(round(x))
    except Exception:
        return None
def safe_str(value):
    if is_missing(value):
        return None
    return str(value)
def normalize_unknowns(series: pd.Series) -> pd.Series:
    unknown_tokens = {
        "Inconnu",
        "inconnu",
        "INCONNU",
        "Unknown",
        "unknown",
        "",
        " ",
        "NULL",
        "None",
        "none",
        "nan",
        "NaN",
        "<NA>",
    }
    s = series.copy()
    if s.dtype == "object" or pd.api.types.is_string_dtype(s):
        s = s.astype("string").str.strip()
        s = s.replace(list(unknown_tokens), np.nan)
    return s.where(pd.notna(s), np.nan)
def clean_category_exact(series: pd.Series) -> pd.Series:
    s = normalize_unknowns(series)
    if s.dtype == "object" or pd.api.types.is_string_dtype(s):
        s = s.astype("string").str.strip()
    return s.astype("object").where(pd.notna(s), np.nan)
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
         .str.replace("ç", "c", regex=False)
         .str.replace("_", " ", regex=False)
         .str.replace("-", " ", regex=False)
    )
    s = s.str.replace(r"\s+", " ", regex=True)
    return s
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

def safe_feature_suffix(value) -> str:
    if is_missing(value):
        return ""

    s = str(value).strip()

    s = (
        s.replace("é", "e")
         .replace("è", "e")
         .replace("ê", "e")
         .replace("à", "a")
         .replace("ç", "c")
         .replace("É", "E")
         .replace("È", "E")
         .replace("Ê", "E")
         .replace("À", "A")
         .replace("Ç", "C")
    )

    s = re.sub(r"[^0-9A-Za-z]+", "_", s)
    s = re.sub(r"_+", "_", s).strip("_")

    return s


def split_multivalue(value) -> list[str]:
    """
    SQL returns values like A | B | C.
    """
    if is_missing(value):
        return []

    parts = [p.strip() for p in str(value).split("|")]
    parts = [
        p for p in parts
        if p and p not in {"Inconnu", "inconnu", "NULL", "None", "nan", "<NA>"}
    ]

    return parts


def add_multihot_columns(
    df: pd.DataFrame,
    source_col: str,
    prefix: str
) -> pd.DataFrame:
    """
    Creates columns:
    analyse__X = 1/0
    laborantin__Y = 1/0
    """
    out = df.copy()

    if source_col not in out.columns:
        out[source_col] = ""

    all_suffixes = set()
    lists = []

    for value in out[source_col].tolist():
        tokens = split_multivalue(value)
        suffixes = []

        for token in tokens:
            suffix = safe_feature_suffix(token)

            if suffix:
                suffixes.append(suffix)
                all_suffixes.add(suffix)

        lists.append(suffixes)

    for suffix in sorted(all_suffixes):
        col = f"{prefix}__{suffix}"
        out[col] = 0

    for idx, suffixes in zip(out.index, lists):
        for suffix in suffixes:
            col = f"{prefix}__{suffix}"

            if col in out.columns:
                out.at[idx, col] = 1
    return out
def load_best_model(stage: str):
    model_path = os.path.join(OUTPUTS_DIR, stage, "models", "best_model.pkl")

    if not os.path.exists(model_path):
        raise FileNotFoundError(f"Modèle introuvable: {model_path}")

    return joblib.load(model_path)
def get_best_model_metadata(stage: str) -> dict:
    summary_path = os.path.join(OUTPUTS_DIR, stage, "tables", "best_model_summary.csv")

    if os.path.exists(summary_path):
        df = pd.read_csv(summary_path)

        if not df.empty:
            row = df.iloc[0].to_dict()

            return {
                "stage": stage,
                "model_family": safe_str(row.get("model_family")),
                "model_name": safe_str(row.get("model_name")),
                "variant": safe_str(row.get("variant")),
            }

    comparaison_path = os.path.join(OUTPUTS_DIR, stage, "tables", "comparaison_modeles.csv")

    if os.path.exists(comparaison_path):
        df = pd.read_csv(comparaison_path)

        if not df.empty:
            df = df.sort_values(
                by=["holdout_MAE", "MAE_mean", "holdout_RMSE"],
                ascending=[True, True, True]
            ).reset_index(drop=True)

            row = df.iloc[0].to_dict()

            return {
                "stage": stage,
                "model_family": safe_str(row.get("model_family")),
                "model_name": safe_str(row.get("model_name")),
                "variant": safe_str(row.get("variant")),
            }

    return {
        "stage": stage,
        "model_family": None,
        "model_name": None,
        "variant": None,
    }


def get_expected_features(model) -> list[str]:
    """
    Extracts raw feature columns expected by the fitted sklearn Pipeline.
    Accepts pipelines using step names:
    - prep
    - preprocessor
    - preprocess
    """
    if hasattr(model, "feature_names_in_"):
        return list(model.feature_names_in_)

    if not hasattr(model, "named_steps"):
        raise ValueError("Le modèle chargé n'est pas un Pipeline sklearn.")

    prep = None

    for step_name in ["prep", "preprocessor", "preprocess"]:
        if step_name in model.named_steps:
            prep = model.named_steps[step_name]
            break

    if prep is None:
        for _, step in model.named_steps.items():
            if hasattr(step, "transformers_"):
                prep = step
                break

    if prep is None:
        raise ValueError("Impossible de trouver le preprocessor dans le Pipeline.")

    features = []

    if hasattr(prep, "transformers_"):
        for name, transformer, cols in prep.transformers_:
            if name == "remainder":
                continue

            if cols is None:
                continue

            if isinstance(cols, (list, tuple, np.ndarray, pd.Index)):
                features.extend(list(cols))
            else:
                features.append(cols)

    features = [str(f) for f in features]

    if not features:
        raise ValueError("Impossible de récupérer les features attendues par le modèle.")

    return features
def build_X_for_model(df_stage: pd.DataFrame, expected_features: list[str]) -> pd.DataFrame:
    X = pd.DataFrame(index=df_stage.index)
    for col in expected_features:
        if col in df_stage.columns:
            X[col] = df_stage[col]
        else:
            if col.startswith("analyse__") or col.startswith("laborantin__"):
                X[col] = 0
            else:
                X[col] = np.nan
    multi_cols = [
        c for c in X.columns
        if c.startswith("analyse__") or c.startswith("laborantin__")
    ]
    for col in multi_cols:
        X[col] = pd.to_numeric(X[col], errors="coerce").fillna(0).astype(int)
    return X
def extract_demandes_en_cours() -> pd.DataFrame:
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
                        'non',
                        'non faisable',
                        'non-faisable',
                        'infaisable',
                        'no',
                        'false',
                        '0'
                    )
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Non'

            WHEN SUM(
                CASE
                    WHEN ddc.faisabilite IS NULL
                      OR TRIM(ddc.faisabilite) = ''
                      OR LOWER(TRIM(ddc.faisabilite)) IN (
                            'oui',
                            'faisable',
                            'yes',
                            'true',
                            '1'
                        )
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Oui'
            ELSE NULL
        END AS faisabilite,
        CASE
            WHEN SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(ddc.validite, ''))) IN (
                        'non',
                        'non valide',
                        'non-valide',
                        'invalide',
                        'no',
                        'false',
                        '0'
                    )
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Non'
            WHEN SUM(
                CASE
                    WHEN ddc.validite IS NULL
                      OR TRIM(ddc.validite) = ''
                      OR LOWER(TRIM(ddc.validite)) IN (
                            'oui',
                            'valide',
                            'yes',
                            'true',
                            '1'
                        )
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Oui'
            ELSE NULL
        END AS validite
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
                        'non',
                        'non conforme',
                        'non-conforme',
                        'nonconforme',
                        'nc',
                        'ko',
                        '0',
                        'false'
                    )
                    OR LOWER(TRIM(COALESCE(de.est_conforme, ''))) LIKE '%%non%%conform%%'
                    THEN 1 ELSE 0
                END
            ) > 0 THEN 'Non'
            WHEN SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(de.est_conforme, ''))) IN (
                        'oui',
                        'conforme',
                        'c',
                        'ok',
                        '1',
                        'true'
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
            SEPARATOR ' | '
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
            ORDER BY dl.id_laborantin_bk
            SEPARATOR ' | '
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
    CASE
        WHEN dstat.statut_demande IS NULL
          OR TRIM(dstat.statut_demande) = ''
            THEN 'Ouverte probable - statut NULL'
        WHEN LOWER(TRIM(dstat.statut_demande)) IN ('en cours', 'encours')
            THEN 'En cours'
        ELSE dstat.statut_demande
    END AS statut_operationnel,
    dt_dem.date_complete AS date_demande,
    COALESCE(dca.faisabilite, 'Oui') AS faisabilite,
    COALESCE(dca.validite, 'Oui') AS validite,
    COALESCE(ech.est_conforme_decision, dstat.conformite) AS est_conforme,
    fda.nb_demandes_analyse,
    fda.nb_analyses,
    fda.nb_ordres,
    fda.nb_resultats,
    fda.nb_echantillons AS nb_fiches_paillasse,
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
    COALESCE(ana.nb_analyses_distinct, 0) AS nb_analyses_distinct,
    ana.analyse_list,
    COALESCE(lab.nb_laborantins_distinct, 0) AS nb_laborantins_distinct,
    lab.laborantin_list,
    dca.ref_demande_client,
    COALESCE(dca.nb_demande_client_liees, 0) AS nb_demande_client_liees
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
LEFT JOIN analyse_agg ana
    ON ana.id_demande_analyse_sk = fda.id_demande_analyse_sk
LEFT JOIN laborantin_agg lab
    ON lab.id_demande_analyse_sk = fda.id_demande_analyse_sk
WHERE dt_dem.date_complete IS NOT NULL
  AND dda.id_demande_analyse_bk IS NOT NULL
  AND dt_dem.date_complete >= '2000-01-01'
  AND dc.id_client_bk IS NOT NULL
  AND dc.id_client_bk <> 0
  AND NOT EXISTS (
      SELECT 1
      FROM fait_rapport fr_done
      WHERE fr_done.id_demande_analyse_sk = fda.id_demande_analyse_sk
        AND fr_done.delai_total_demande_livraison_jours IS NOT NULL
        AND fr_done.delai_total_demande_livraison_jours >= 0
  )
  AND (
        COALESCE(ech.est_conforme_decision, dstat.conformite) IS NULL
        OR LOWER(TRIM(COALESCE(ech.est_conforme_decision, dstat.conformite))) NOT IN (
            'non',
            'non conforme',
            'non-conforme',
            'nonconforme',
            'nc',
            'ko',
            '0',
            'false'
        )
      )
  AND (
        dstat.statut_demande IS NULL
        OR TRIM(dstat.statut_demande) = ''
        OR LOWER(TRIM(dstat.statut_demande)) IN ('en cours', 'encours')
        OR LOWER(TRIM(dstat.statut_demande)) NOT IN (
            'cloture',
            'clôturé',
            'cloturé',
            'termine',
            'terminé',
            'annule',
            'annulé',
            'rejete',
            'rejeté',
            'refuse',
            'refusé',
            'valide',
            'validé'
        )
      )

  AND (
        dstat.statut_rapport IS NULL
        OR LOWER(TRIM(dstat.statut_rapport)) NOT IN (
            'final',
            'valide',
            'validé',
            'livre',
            'livré',
            'cloture',
            'clôturé',
            'cloturé'
        )
      )
"""
    print("Extraction des demandes en cours...")
    df = pd.read_sql(query, engine)
    print(f"Demandes en cours extraites avant filtre Python: {df.shape}")
    df["date_demande"] = pd.to_datetime(df["date_demande"], errors="coerce")
    df = df[
        df["date_demande"].notna()
        & (df["date_demande"] >= pd.Timestamp("2000-01-01"))
    ].copy()
    if df.empty:
        print("Aucune demande en cours après nettoyage des dates.")
        return df
    if USE_DATA_REFERENCE_DATE:
        reference_date = df["date_demande"].max().normalize()
    else:
        reference_date = pd.Timestamp.today().normalize()
    date_min = reference_date - pd.DateOffset(months=LOOKBACK_MONTHS)
    date_max = reference_date
    df = df[
        (df["date_demande"] >= date_min)
        & (df["date_demande"] <= date_max)
    ].copy()
    df["date_reference_prediction"] = reference_date
    print(f"Date de référence utilisée pour la prédiction: {reference_date.date()}")
    print(f"Fenêtre retenue: {date_min.date()} -> {date_max.date()}")
    print(f"Demandes en cours après filtre {LOOKBACK_MONTHS} mois basé dataset: {df.shape}")
    return df
def prepare_features(df: pd.DataFrame) -> pd.DataFrame:
    out = df.copy()
    text_defaults = {
        "client_nom": "Inconnu",
        "type_client": "Inconnu",
        "secteur_nom": "Inconnu",
        "statut_demande": "Inconnu",
        "statut_ordre": "Inconnu",
        "statut_rapport": "Inconnu",
        "statut_prediction": "Inconnu",
        "statut_operationnel": "Ouverte probable - statut NULL",
        "type_echantillon": "Inconnu",
        "nom_produit": "Inconnu",
        "code_produit_demandeur": "Inconnu",
        "ref_labo": "Inconnu",
        "code_ref_labo": "Inconnu",
        "ref_demande_client": "Inconnu",
        "reference_demande": "Inconnu",
        "analyse_list": "",
        "laborantin_list": "",
    }
    for col, default in text_defaults.items():
        if col in out.columns:
            out[col] = out[col].fillna(default)
    numeric_defaults = {
        "nb_demandes_analyse": 1,
        "nb_analyses": 0,
        "nb_ordres": 0,
        "nb_resultats": 0,
        "nb_fiches_paillasse": 0,
        "nb_analyses_distinct": 0,
        "nb_laborantins_distinct": 0,
        "nb_demande_client_liees": 0,
    }
    for col, default in numeric_defaults.items():
        if col in out.columns:
            out[col] = out[col].fillna(default)
    out["date_demande"] = pd.to_datetime(out["date_demande"], errors="coerce")
    if "date_reference_prediction" in out.columns:
        out["date_reference_prediction"] = pd.to_datetime(
            out["date_reference_prediction"],
            errors="coerce"
        )
    if "date_reception_echantillon" in out.columns:
        out["date_reception_echantillon"] = pd.to_datetime(
            out["date_reception_echantillon"],
            errors="coerce"
        )
    client_raw = out["id_client"].astype("string")
    client_raw = client_raw.replace(["0", "0.0", "<NA>", "nan", "None"], np.nan)
    out["client_ml"] = clean_category_exact(client_raw)
    out["secteur_ml"] = clean_category_exact(out["secteur_nom"])
    out["faisabilite_ml"] = yes_no_clean(out["faisabilite"])
    out["validite_ml"] = yes_no_clean(out["validite"])
    out["est_conforme_ml"] = yes_no_clean(out["est_conforme"])
    out["type_echantillon_ml"] = clean_category_exact(out["type_echantillon"])
    out["nom_produit_ml"] = clean_category_exact(out["nom_produit"])
    out["code_ref_labo_ml"] = clean_category_exact(out["code_ref_labo"])
    out["mois_demande"] = out["date_demande"].dt.month
    out["trimestre_demande"] = out["date_demande"].dt.quarter
    out["jour_semaine_demande"] = out["date_demande"].dt.dayofweek
    out = add_multihot_columns(out, "analyse_list", "analyse")
    out = add_multihot_columns(out, "laborantin_list", "laborantin")
    required_optional_cols = [
        "delai_affectation",
        "delai_debut_analyse",
        "delai_reception",
        "delai_total_demande",
    ]
    for col in required_optional_cols:
        if col not in out.columns:
            out[col] = np.nan
    return out
def choose_stage(row) -> str:
    """
    Détermination du stade opérationnel d'une demande en cours.

    Règle retenue :
    - avancee : résultat disponible ou rapport produit / validé.
    - intermediaire : analyse demandée connue dans fait_demande_client,
      ou analyse/ordre connu, ou affectation/début d'analyse disponible.
    - precoce : aucune analyse connue, aucune affectation, aucun début d'analyse,
      aucun résultat et aucun rapport.
    """

    delai_affectation = safe_float(row.get("delai_affectation"))
    delai_debut_analyse = safe_float(row.get("delai_debut_analyse"))

    nb_resultats = safe_float(row.get("nb_resultats")) or 0
    nb_ordres = safe_float(row.get("nb_ordres")) or 0
    nb_analyses = safe_float(row.get("nb_analyses")) or 0
    nb_analyses_distinct = safe_float(row.get("nb_analyses_distinct")) or 0
    nb_demande_client_liees = safe_float(row.get("nb_demande_client_liees")) or 0

    analyse_list = row.get("analyse_list")
    statut_rapport = safe_str(row.get("statut_rapport"))

    statut_rapport_norm = ""
    if statut_rapport is not None:
        statut_rapport_norm = (
            statut_rapport.strip()
            .lower()
            .replace("é", "e")
            .replace("è", "e")
            .replace("ê", "e")
            .replace("à", "a")
            .replace("ç", "c")
        )

    has_result_or_report = (
        nb_resultats > 0
        or statut_rapport_norm in {
            "provisoire",
            "rapport provisoire",
            "en validation",
            "valide",
            "validé",
            "validee",
            "final",
            "livre",
            "livré",
            "livree",
        }
    )

    has_analyse = (
        nb_demande_client_liees > 0
        or nb_analyses > 0
        or nb_ordres > 0
        or nb_analyses_distinct > 0
        or not is_missing(analyse_list)
    )

    has_affectation = delai_affectation is not None
    has_debut_analyse = delai_debut_analyse is not None

    if has_result_or_report:
        return "avancee"

    if has_analyse or has_affectation or has_debut_analyse:
        return "intermediaire"

    return "precoce"
def compute_risk(row) -> str:
    jours_retard = safe_int(row.get("jours_retard_predit"))
    jours_restants = safe_int(row.get("jours_restants_predits"))
    if jours_retard is not None and jours_retard > 0:
        return "Critique"
    if jours_restants is None:
        return "Inconnu"
    if jours_restants <= 2:
        return "Eleve"
    if jours_restants <= 7:
        return "Moyen"
    return "Faible"
def predict_for_stage(df: pd.DataFrame, stage: str) -> pd.DataFrame:
    df_stage = df[df["stage"] == stage].copy()
    if df_stage.empty:
        print(f"{stage}: 0 demande")
        return pd.DataFrame()
    model = load_best_model(stage)
    meta = get_best_model_metadata(stage)
    expected_features = get_expected_features(model)
    X = build_X_for_model(df_stage, expected_features)
    y_pred = model.predict(X)
    df_stage["delai_predit_jours"] = y_pred
    df_stage["delai_predit_jours"] = pd.to_numeric(
        df_stage["delai_predit_jours"],
        errors="coerce"
    )
    df_stage["delai_predit_jours"] = df_stage["delai_predit_jours"].clip(lower=0)
    if "date_reference_prediction" in df_stage.columns:
        reference_date = pd.to_datetime(
            df_stage["date_reference_prediction"].iloc[0],
            errors="coerce"
        )
        if pd.isna(reference_date):
            reference_date = pd.Timestamp.today().normalize()
    else:
        reference_date = pd.Timestamp.today().normalize()
    reference_date = reference_date.normalize()
    df_stage["date_livraison_estimee"] = (
        df_stage["date_demande"]
        + pd.to_timedelta(df_stage["delai_predit_jours"].round().astype(int), unit="D")
    )
    df_stage["jours_restants_predits"] = (
        df_stage["date_livraison_estimee"] - reference_date
    ).dt.days
    df_stage["jours_retard_predit"] = (
        reference_date - df_stage["date_livraison_estimee"]
    ).dt.days.clip(lower=0)
    df_stage["retard_predit_flag"] = (
        df_stage["jours_retard_predit"] > 0
    ).astype(int)
    df_stage["niveau_risque"] = df_stage.apply(compute_risk, axis=1)
    df_stage["model_family"] = meta.get("model_family")
    df_stage["model_name"] = meta.get("model_name")
    df_stage["variant"] = meta.get("variant")
    print(
        f"{stage}: {len(df_stage)} demandes prédites avec "
        f"{meta.get('model_name')} | {meta.get('variant')}"
    )
    return df_stage
def run_predictions(df: pd.DataFrame) -> pd.DataFrame:
    if df.empty:
        return pd.DataFrame()
    df = prepare_features(df)
    df = df[
        df["date_demande"].notna()
        & df["client_ml"].notna()
        & (df["est_conforme_ml"] != "Non")
        & (df["faisabilite_ml"] != "Non")
        & (df["validite_ml"] != "Non")
    ].copy()
    df["stage"] = df.apply(choose_stage, axis=1)
    print("\nRépartition des demandes par stade:")
    print(df["stage"].value_counts(dropna=False))

    diagnostic_cols = [
        "nb_demande_client_liees",
        "nb_analyses",
        "nb_ordres",
        "nb_resultats",
        "nb_analyses_distinct",
        "delai_affectation",
        "delai_debut_analyse",
    ]
    diagnostic_cols = [c for c in diagnostic_cols if c in df.columns]
    if diagnostic_cols:
        print("\nDiagnostic stades - sommes par stade:")
        print(df.groupby("stage", dropna=False)[diagnostic_cols].sum(numeric_only=True))

    results = []
    for stage in STAGES:
        pred_stage = predict_for_stage(df, stage)

        if not pred_stage.empty:
            results.append(pred_stage)
    if not results:
        return pd.DataFrame()
    pred_df = pd.concat(results, ignore_index=True)
    return pred_df
def export_predictions(pred_df: pd.DataFrame):
    if pred_df.empty:
        pd.DataFrame().to_csv(PREDICTIONS_OUTPUT_PATH, index=False, encoding="utf-8-sig")
        print("Aucune prédiction en cours.")
        return
    for col in ["date_demande", "date_reference_prediction", "date_livraison_estimee"]:
        if col in pred_df.columns:
            pred_df[col] = pd.to_datetime(pred_df[col], errors="coerce").dt.strftime("%Y-%m-%d")
    preferred_cols = [
        "id_demande",
        "id_demande_analyse_sk",
        "reference_demande",
        "stage",
        "model_family",
        "model_name",
        "variant",
        "id_client_sk",
        "id_client",
        "client_nom",
        "id_secteur_sk",
        "id_secteur",
        "secteur_nom",
        "id_statut_sk",
        "statut_demande",
        "statut_operationnel",
        "statut_rapport",
        "date_demande",
        "date_reference_prediction",
        "delai_predit_jours",
        "date_livraison_estimee",
        "jours_restants_predits",
        "jours_retard_predit",
        "retard_predit_flag",
        "niveau_risque",
        "nb_analyses",
        "nb_ordres",
        "nb_resultats",
        "nb_fiches_paillasse",
        "nb_analyses_distinct",
        "nb_demande_client_liees",
        "nb_laborantins_distinct",
        "faisabilite_ml",
        "validite_ml",
        "est_conforme_ml",
        "type_echantillon_ml",
        "nom_produit_ml",
        "code_ref_labo_ml",
        "analyse_list",
        "laborantin_list",
    ]
    existing_preferred = [c for c in preferred_cols if c in pred_df.columns]
    remaining_cols = [c for c in pred_df.columns if c not in existing_preferred]
    export_df = pred_df[existing_preferred + remaining_cols].copy()
    export_df.to_csv(PREDICTIONS_OUTPUT_PATH, index=False, encoding="utf-8-sig")
    print(f"\nPrédictions en cours sauvegardées: {PREDICTIONS_OUTPUT_PATH}")
    print(f"Shape: {export_df.shape}")
    preview_cols = [
        "id_demande",
        "id_demande_analyse_sk",
        "stage",
        "model_name",
        "variant",
        "date_demande",
        "date_reference_prediction",
        "delai_predit_jours",
        "date_livraison_estimee",
        "jours_restants_predits",
        "jours_retard_predit",
        "retard_predit_flag",
        "niveau_risque",
    ]
    preview_cols = [c for c in preview_cols if c in export_df.columns]
    print(export_df[preview_cols].head(30))

def main():
    df = extract_demandes_en_cours()
    pred_df = run_predictions(df)
    export_predictions(pred_df)
if __name__ == "__main__":
    main()
