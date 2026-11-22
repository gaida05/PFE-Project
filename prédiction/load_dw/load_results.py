import os
import sys
import math
import numpy as np
import pandas as pd
from datetime import datetime
from sqlalchemy import create_engine, text
BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
VIEWS_SQL_PATH = os.path.join(BASE_DIR, "load_dw", "grafana_views.sql")
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)
DB_URL = "mysql+pymysql://root:@localhost:3306/dw_labo"
DATA_DIR = os.path.join(BASE_DIR, "data")
OUTPUTS_DIR = os.path.join(BASE_DIR, "outputs")
STAGES = ["precoce", "intermediaire", "avancee"]
RETARD_THRESHOLD_JOURS = 10.0
PREDICTIONS_EN_COURS_PATH = os.path.join(DATA_DIR, "predictions_en_cours.csv")
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
        return value.strip() in {"", "nan", "NaN", "None", "NULL", "<NA>"}
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


def safe_date(value, min_year=2000):
    """
    Convertit en date SQL.
    Les dates absurdes type 1900-01-15 sont mises à NULL.
    """
    if is_missing(value):
        return None

    dt = pd.to_datetime(value, errors="coerce")

    if pd.isna(dt):
        return None

    if dt.year < min_year:
        return None

    return dt.date()


def clean_dataframe_for_sql(df: pd.DataFrame) -> pd.DataFrame:
    """
    Remplace NaN / NaT / pd.NA par None pour MySQL.
    """
    if df.empty:
        return df

    return df.replace({np.nan: None}).where(pd.notnull(df), None)


def table_columns(table_name: str) -> list[str]:
    query = text("""
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
        ORDER BY ORDINAL_POSITION
    """)

    with engine.begin() as conn:
        rows = conn.execute(query, {"table_name": table_name}).fetchall()

    return [row[0] for row in rows]


def build_insert_sql(table_name: str, insert_cols: list[str]):
    cols_sql = ",\n            ".join(insert_cols)
    vals_sql = ",\n            ".join([f":{c}" for c in insert_cols])

    return text(f"""
        INSERT INTO {table_name} (
            {cols_sql}
        )
        VALUES (
            {vals_sql}
        )
    """)


def get_id_temps_from_date(date_value):
    """
    Recherche id_temps dans dim_temps.
    Si la date n'existe pas dans dim_temps, fallback YYYYMMDD.
    """
    dt = pd.to_datetime(date_value, errors="coerce")

    if pd.isna(dt):
        dt = pd.Timestamp.today().normalize()

    date_complete = dt.date()

    query = text("""
        SELECT id_temps
        FROM dim_temps
        WHERE date_complete = :date_complete
        LIMIT 1
    """)

    with engine.begin() as conn:
        row = conn.execute(query, {"date_complete": date_complete}).fetchone()

    if row:
        return safe_int(row[0])

    return int(dt.strftime("%Y%m%d"))


def get_today_id_temps():
    return get_id_temps_from_date(pd.Timestamp.today().normalize())


def ensure_dim_temps_id(conn, id_temps):
    """
    Garantit que id_temps existe dans dim_temps avant insertion dans fait_prediction.
    Exemple : 20260506 -> 2026-05-06.
    Corrige l'erreur FK fk_fait_prediction_temps.
    """
    if id_temps is None:
        return None

    try:
        id_temps = int(id_temps)
    except Exception:
        return None

    date_str = str(id_temps)

    if len(date_str) != 8:
        return None

    try:
        date_complete = datetime.strptime(date_str, "%Y%m%d").date()
    except Exception:
        return None

    mois_noms = {
        1: "Janvier",
        2: "Fevrier",
        3: "Mars",
        4: "Avril",
        5: "Mai",
        6: "Juin",
        7: "Juillet",
        8: "Aout",
        9: "Septembre",
        10: "Octobre",
        11: "Novembre",
        12: "Decembre",
    }

    conn.execute(
        text("""
            INSERT INTO dim_temps (
                id_temps,
                date_complete,
                jour,
                mois,
                nom_mois,
                trimestre,
                annee,
                semaine,
                mois_annee
            )
            VALUES (
                :id_temps,
                :date_complete,
                :jour,
                :mois,
                :nom_mois,
                :trimestre,
                :annee,
                :semaine,
                :mois_annee
            )
            ON DUPLICATE KEY UPDATE
                date_complete = VALUES(date_complete),
                jour = VALUES(jour),
                mois = VALUES(mois),
                nom_mois = VALUES(nom_mois),
                trimestre = VALUES(trimestre),
                annee = VALUES(annee),
                semaine = VALUES(semaine),
                mois_annee = VALUES(mois_annee)
        """),
        {
            "id_temps": id_temps,
            "date_complete": date_complete,
            "jour": date_complete.day,
            "mois": date_complete.month,
            "nom_mois": mois_noms.get(date_complete.month, "Inconnu"),
            "trimestre": ((date_complete.month - 1) // 3) + 1,
            "annee": date_complete.year,
            "semaine": int(date_complete.isocalendar().week),
            "mois_annee": f"{date_complete.month:02d}-{date_complete.year}",
        },
    )

    return id_temps



def load_csv_if_exists(path: str) -> pd.DataFrame:
    if not os.path.exists(path):
        print(f"[WARN] Fichier introuvable: {path}")
        return pd.DataFrame()

    df = pd.read_csv(path)
    return clean_dataframe_for_sql(df)


def load_stage_files(stage: str):
    tables_dir = os.path.join(OUTPUTS_DIR, stage, "tables")

    comparaison_path = os.path.join(tables_dir, "comparaison_modeles.csv")
    holdout_path = os.path.join(tables_dir, "predictions_holdout.csv")
    importance_path = os.path.join(tables_dir, "feature_importance.csv")

    comparaison_df = load_csv_if_exists(comparaison_path)
    holdout_df = load_csv_if_exists(holdout_path)
    importance_df = load_csv_if_exists(importance_path)

    return comparaison_df, holdout_df, importance_df


def infer_model_family(model_name: str):
    if is_missing(model_name):
        return None

    name = str(model_name).lower()

    if "xgboost" in name or "xgb" in name:
        return "xgb"

    if "randomforest" in name or "forest" in name:
        return "rf"

    if "ridge" in name or "linear" in name:
        return "linear"

    return None


def ensure_model_family(df: pd.DataFrame) -> pd.DataFrame:
    if df.empty:
        return df

    out = df.copy()

    if "model_family" not in out.columns:
        out["model_family"] = None

    out["model_family"] = out.apply(
        lambda row: safe_str(row.get("model_family")) or infer_model_family(row.get("model_name")),
        axis=1
    )

    return out
def upsert_dim_modele(comparaison_df: pd.DataFrame):
    if comparaison_df.empty:
        return
    comparaison_df = ensure_model_family(comparaison_df)
    insert_sql = text("""
        INSERT INTO dim_modele (
            stage_prediction,
            model_family,
            model_name,
            variant,
            hyperparams_json,
            mae_mean,
            rmse_mean,
            r2_mean,
            holdout_mae,
            holdout_rmse,
            holdout_r2,
            date_entrainement
        )
        VALUES (
            :stage_prediction,
            :model_family,
            :model_name,
            :variant,
            :hyperparams_json,
            :mae_mean,
            :rmse_mean,
            :r2_mean,
            :holdout_mae,
            :holdout_rmse,
            :holdout_r2,
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            hyperparams_json = VALUES(hyperparams_json),
            mae_mean = VALUES(mae_mean),
            rmse_mean = VALUES(rmse_mean),
            r2_mean = VALUES(r2_mean),
            holdout_mae = VALUES(holdout_mae),
            holdout_rmse = VALUES(holdout_rmse),
            holdout_r2 = VALUES(holdout_r2),
            date_entrainement = NOW()
    """)
    with engine.begin() as conn:
        for _, row in comparaison_df.iterrows():
            conn.execute(insert_sql, {
                "stage_prediction": safe_str(row.get("stage")),
                "model_family": safe_str(row.get("model_family")),
                "model_name": safe_str(row.get("model_name")),
                "variant": safe_str(row.get("variant")),
                "hyperparams_json": safe_str(row.get("hyperparams")) or "{}",
                "mae_mean": safe_float(row.get("MAE_mean")),
                "rmse_mean": safe_float(row.get("RMSE_mean")),
                "r2_mean": safe_float(row.get("R2_mean")),
                "holdout_mae": safe_float(row.get("holdout_MAE")),
                "holdout_rmse": safe_float(row.get("holdout_RMSE")),
                "holdout_r2": safe_float(row.get("holdout_R2")),
            })
def get_model_mapping() -> pd.DataFrame:
    query = """
        SELECT
            id_modele_sk,
            stage_prediction,
            model_family,
            model_name,
            variant
        FROM dim_modele
    """

    return clean_dataframe_for_sql(pd.read_sql(query, engine))
def infer_variable_type(variable_name: str):
    if is_missing(variable_name):
        return None
    v = str(variable_name)
    if v.startswith("analyse__"):
        return "analyse_multi_hot"
    if v.startswith("laborantin__"):
        return "laborantin_multi_hot"
    categorical_vars = {
        "client_ml",
        "secteur_ml",
        "faisabilite_ml",
        "validite_ml",
        "est_conforme_ml",
        "type_echantillon_ml",
        "nom_produit_ml",
        "code_ref_labo_ml",
        "analyse_ml",
        "laborantin_ml",
    }
    if v in categorical_vars:
        return "categorielle"
    if v.startswith(("nb_", "delai_", "mois_", "jour_", "trimestre_")):
        return "numerique"
    return "inconnu"
def infer_variable_level(variable_name: str):
    if is_missing(variable_name):
        return None
    v = str(variable_name)
    if v.startswith("analyse__"):
        return "intermediaire"
    if v.startswith("laborantin__"):
        return "intermediaire"
    if v in {
        "delai_debut_analyse",
    }:
        return "avancee"
    if v in {
        "nom_produit_ml",
        "code_ref_labo_ml",
        "nb_fiches_paillasse",
        "nb_ordres",
        "delai_affectation",
        "nb_analyses_distinct",
        "nb_laborantins_distinct",
        "analyse_ml",
        "laborantin_ml",
    }:
        return "intermediaire"

    if v in {
        "client_ml",
        "secteur_ml",
        "faisabilite_ml",
        "validite_ml",
        "est_conforme_ml",
        "type_echantillon_ml",
        "nb_analyses",
        "mois_demande",
        "trimestre_demande",
        "jour_semaine_demande",
        "delai_reception",
    }:
        return "precoce"
    return "inconnu"
def upsert_dim_variable(importance_df: pd.DataFrame):
    if importance_df.empty or "feature" not in importance_df.columns:
        return
    distinct_features = (
        importance_df[["feature"]]
        .dropna()
        .drop_duplicates()
        .rename(columns={"feature": "nom_variable"})
        .copy()
    )
    distinct_features["type_variable"] = distinct_features["nom_variable"].apply(infer_variable_type)
    distinct_features["niveau_variable"] = distinct_features["nom_variable"].apply(infer_variable_level)
    distinct_features = clean_dataframe_for_sql(distinct_features)
    insert_sql = text("""
        INSERT INTO dim_variable (
            nom_variable,
            type_variable,
            niveau_variable
        )
        VALUES (
            :nom_variable,
            :type_variable,
            :niveau_variable
        )
        ON DUPLICATE KEY UPDATE
            type_variable = VALUES(type_variable),
            niveau_variable = VALUES(niveau_variable)
    """)
    with engine.begin() as conn:
        for _, row in distinct_features.iterrows():
            conn.execute(insert_sql, {
                "nom_variable": safe_str(row.get("nom_variable")),
                "type_variable": safe_str(row.get("type_variable")),
                "niveau_variable": safe_str(row.get("niveau_variable")),
            })
def get_variable_mapping() -> pd.DataFrame:
    query = """
        SELECT
            id_variable_sk,
            nom_variable
        FROM dim_variable
    """
    return clean_dataframe_for_sql(pd.read_sql(query, engine))

def get_demande_analyse_sk(conn, row):
    """
    Résout id_demande_analyse_sk pour fait_prediction.
    Priorité :
    1. id_demande_analyse_sk déjà présent
    2. id_demande / id_demande_bk via dim_demande_analyse
    """
    direct_sk = safe_int(row.get("id_demande_analyse_sk"))

    if direct_sk is not None:
        return direct_sk

    id_demande = safe_int(row.get("id_demande"))

    if id_demande is None:
        id_demande = safe_int(row.get("id_demande_bk"))

    if id_demande is None:
        return None

    query = text("""
        SELECT id_demande_analyse_sk
        FROM dim_demande_analyse
        WHERE id_demande_analyse_bk = :id_demande
        LIMIT 1
    """)

    result = conn.execute(query, {"id_demande": id_demande}).fetchone()
    if result:
        return safe_int(result[0])

    return None

def reset_predictions_for_models(conn, model_ids, prediction_type: str):
    cols = table_columns("fait_prediction")
    unique_ids = [int(x) for x in pd.Series(model_ids).dropna().unique()]
    if not unique_ids:
        return
    for model_id in unique_ids:
        if "type_prediction" in cols:
            conn.execute(
                text("""
                    DELETE FROM fait_prediction
                    WHERE id_modele_sk = :id_modele_sk
                      AND type_prediction = :type_prediction
                """),
                {
                    "id_modele_sk": model_id,
                    "type_prediction": prediction_type,
                }
            )
        else:
            if prediction_type == "HOLDOUT":
                conn.execute(
                    text("""
                        DELETE FROM fait_prediction
                        WHERE id_modele_sk = :id_modele_sk
                          AND delai_reel_jours IS NOT NULL
                    """),
                    {"id_modele_sk": model_id}
                )
            else:
                conn.execute(
                    text("""
                        DELETE FROM fait_prediction
                        WHERE id_modele_sk = :id_modele_sk
                          AND delai_reel_jours IS NULL
                    """),
                    {"id_modele_sk": model_id}
                )

def reset_en_cours_predictions(conn):
    cols = table_columns("fait_prediction")

    if "type_prediction" in cols:
        conn.execute(text("""
            DELETE FROM fait_prediction
            WHERE type_prediction = 'EN_COURS'
        """))
    else:
        conn.execute(text("""
            DELETE FROM fait_prediction
            WHERE delai_reel_jours IS NULL
        """))
def reset_importance_for_models(conn, model_ids):
    unique_ids = [int(x) for x in pd.Series(model_ids).dropna().unique()]
    if not unique_ids:
        return
    for model_id in unique_ids:
        conn.execute(
            text("""
                DELETE FROM fait_importance_variable
                WHERE id_modele_sk = :id_modele_sk
            """),
            {"id_modele_sk": model_id}
        )
def insert_prediction_row(conn, values: dict):
    existing_cols = table_columns("fait_prediction")
    preferred_cols = [
        "id_temps_prediction",
        "id_modele_sk",
        "id_client_sk",
        "id_secteur_sk",
        "id_statut_sk",
        "id_demande_analyse_sk",
        "id_demande_bk",
        "type_prediction",
        "delai_predit_jours",
        "delai_reel_jours",
        "erreur_absolue",
        "erreur_signee",
        "retard_predit_flag",
        "retard_reel_flag",
        "date_livraison_estimee",
        "jours_restants_predits",
        "jours_retard_predit",
        "niveau_risque",
    ]
    insert_cols = [c for c in preferred_cols if c in existing_cols and c in values]
    if not insert_cols:
        raise RuntimeError("Aucune colonne compatible trouvée pour fait_prediction.")
    insert_sql = build_insert_sql("fait_prediction", insert_cols)

    # FK fix: id_temps_prediction must exist in dim_temps before insertion.
    if "id_temps_prediction" in insert_cols:
        ensure_dim_temps_id(conn, values.get("id_temps_prediction"))

    conn.execute(insert_sql, {c: values.get(c) for c in insert_cols})
def prepare_model_merge(df: pd.DataFrame) -> pd.DataFrame:
    if df.empty:
        return df
    df = ensure_model_family(df)
    model_map = get_model_mapping()
    merged = df.merge(
        model_map,
        left_on=["stage", "model_family", "model_name", "variant"],
        right_on=["stage_prediction", "model_family", "model_name", "variant"],
        how="left"
    )
    return clean_dataframe_for_sql(merged)
def insert_holdout_predictions(holdout_df: pd.DataFrame):
    if holdout_df.empty:
        return
    pred_df = prepare_model_merge(holdout_df)
    with engine.begin() as conn:
        reset_predictions_for_models(conn, pred_df["id_modele_sk"], "HOLDOUT")
        inserted = 0
        skipped = 0
        for _, row in pred_df.iterrows():
            id_modele_sk = safe_int(row.get("id_modele_sk"))
            if id_modele_sk is None:
                skipped += 1
                print(
                    "[WARN] HOLDOUT ignoré : modèle introuvable "
                    f"stage={row.get('stage')} model={row.get('model_name')} variant={row.get('variant')}"
                )
                continue
            id_demande_analyse_sk = get_demande_analyse_sk(conn, row)
            if id_demande_analyse_sk is None:
                skipped += 1
                print(
                    "[WARN] HOLDOUT ignoré : id_demande_analyse_sk introuvable "
                    f"id_demande={row.get('id_demande')}"
                )
                continue
            y_pred = safe_float(row.get("y_pred"))
            y_true = safe_float(row.get("y_true"))
            id_temps_prediction = get_id_temps_from_date(row.get("date_demande"))
            values = {
                "id_temps_prediction": safe_int(id_temps_prediction),
                "id_modele_sk": id_modele_sk,
                "id_client_sk": safe_int(row.get("id_client_sk")),
                "id_secteur_sk": safe_int(row.get("id_secteur_sk")),
                "id_statut_sk": safe_int(row.get("id_statut_sk")),
                "id_demande_analyse_sk": id_demande_analyse_sk,
                "id_demande_bk": safe_int(row.get("id_demande")),
                "type_prediction": "HOLDOUT",
                "delai_predit_jours": y_pred,
                "delai_reel_jours": y_true,
                "erreur_absolue": safe_float(row.get("erreur_absolue")),
                "erreur_signee": safe_float(row.get("erreur_signee")),
                "retard_predit_flag": 1 if y_pred is not None and y_pred > RETARD_THRESHOLD_JOURS else 0,
                "retard_reel_flag": 1 if y_true is not None and y_true > RETARD_THRESHOLD_JOURS else 0,
                "date_livraison_estimee": None,
                "jours_restants_predits": None,
                "jours_retard_predit": None,
                "niveau_risque": None,
            }

            insert_prediction_row(conn, values)
            inserted += 1

        print(f"[HOLDOUT] lignes insérées: {inserted}, ignorées: {skipped}")

def insert_fait_importance_variable(importance_df: pd.DataFrame):
    if importance_df.empty:
        return
    required = {"feature", "importance", "stage", "model_family", "model_name", "variant"}
    if not required.issubset(set(importance_df.columns)):
        print("[WARN] importance_df colonnes insuffisantes, import ignoré.")
        return
    importance_df = ensure_model_family(importance_df)
    model_map = get_model_mapping()
    variable_map = get_variable_mapping()
    enriched_df = importance_df.merge(
        model_map,
        left_on=["stage", "model_family", "model_name", "variant"],
        right_on=["stage_prediction", "model_family", "model_name", "variant"],
        how="left"
    )
    enriched_df = enriched_df.merge(
        variable_map,
        left_on="feature",
        right_on="nom_variable",
        how="left"
    )
    enriched_df = enriched_df.sort_values(
        by=["stage", "model_family", "model_name", "variant", "importance"],
        ascending=[True, True, True, True, False]
    ).copy()
    enriched_df["rang_variable"] = (
        enriched_df
        .groupby(["stage", "model_family", "model_name", "variant"])
        .cumcount() + 1
    )
    enriched_df = clean_dataframe_for_sql(enriched_df)
    insert_sql = text("""
        INSERT INTO fait_importance_variable (
            id_modele_sk,
            id_variable_sk,
            rang_variable,
            importance,
            date_entrainement
        )
        VALUES (
            :id_modele_sk,
            :id_variable_sk,
            :rang_variable,
            :importance,
            NOW()
        )
    """)

    with engine.begin() as conn:
        reset_importance_for_models(conn, enriched_df["id_modele_sk"])
        inserted = 0
        skipped = 0
        for _, row in enriched_df.iterrows():
            id_modele_sk = safe_int(row.get("id_modele_sk"))
            id_variable_sk = safe_int(row.get("id_variable_sk"))

            if id_modele_sk is None or id_variable_sk is None:
                skipped += 1
                continue
            conn.execute(insert_sql, {
                "id_modele_sk": id_modele_sk,
                "id_variable_sk": id_variable_sk,
                "rang_variable": safe_int(row.get("rang_variable")),
                "importance": safe_float(row.get("importance")),
            })
            inserted += 1
        print(f"[IMPORTANCE] lignes insérées: {inserted}, ignorées: {skipped}")
def load_predictions_en_cours() -> pd.DataFrame:
    if not os.path.exists(PREDICTIONS_EN_COURS_PATH):
        print(f"[WARN] Fichier predictions_en_cours introuvable: {PREDICTIONS_EN_COURS_PATH}")
        return pd.DataFrame()
    df = pd.read_csv(PREDICTIONS_EN_COURS_PATH)
    df = ensure_model_family(df)
    return clean_dataframe_for_sql(df)
def insert_en_cours_predictions(pred_df: pd.DataFrame):
    if pred_df.empty:
        print("[EN_COURS] Aucune prédiction à charger.")
        return
    pred_df = prepare_model_merge(pred_df)
    with engine.begin() as conn:
        reset_en_cours_predictions(conn)
        inserted = 0
        skipped = 0
        for _, row in pred_df.iterrows():
            id_modele_sk = safe_int(row.get("id_modele_sk"))
            if id_modele_sk is None:
                skipped += 1
                print(
                    "[WARN] EN_COURS ignoré : modèle introuvable "
                    f"stage={row.get('stage')} model={row.get('model_name')} variant={row.get('variant')}"
                )
                continue
            id_demande_analyse_sk = get_demande_analyse_sk(conn, row)
            if id_demande_analyse_sk is None:
                skipped += 1
                print(
                    "[WARN] EN_COURS ignoré : id_demande_analyse_sk introuvable "
                    f"id_demande={row.get('id_demande')}"
                )
                continue

            y_pred = safe_float(row.get("delai_predit_jours"))

            if y_pred is None:
                y_pred = safe_float(row.get("y_pred"))

            retard_predit_flag = safe_int(row.get("retard_predit_flag"))

            if retard_predit_flag is None:
                retard_predit_flag = 1 if y_pred is not None and y_pred > RETARD_THRESHOLD_JOURS else 0

            date_livraison_estimee = safe_date(row.get("date_livraison_estimee"))
            if date_livraison_estimee is None:
                jours_restants_predits = None
                jours_retard_predit = None
                niveau_risque = None
            else:
                jours_restants_predits = safe_int(row.get("jours_restants_predits"))
                jours_retard_predit = safe_int(row.get("jours_retard_predit"))
                niveau_risque = safe_str(row.get("niveau_risque"))

            values = {
                "id_temps_prediction": safe_int(get_today_id_temps()),
                "id_modele_sk": id_modele_sk,
                "id_client_sk": safe_int(row.get("id_client_sk")),
                "id_secteur_sk": safe_int(row.get("id_secteur_sk")),
                "id_statut_sk": safe_int(row.get("id_statut_sk")),
                "id_demande_analyse_sk": id_demande_analyse_sk,
                "id_demande_bk": safe_int(row.get("id_demande")),
                "type_prediction": "EN_COURS",
                "delai_predit_jours": y_pred,
                "delai_reel_jours": None,
                "erreur_absolue": None,
                "erreur_signee": None,
                "retard_predit_flag": retard_predit_flag,

                # Important :
                # EN_COURS n'a pas encore de délai réel, mais la colonne est NOT NULL.
                # On met 0 par défaut.
                "retard_reel_flag": 0,

                "date_livraison_estimee": date_livraison_estimee,
                "jours_restants_predits": jours_restants_predits,
                "jours_retard_predit": jours_retard_predit,
                "niveau_risque": niveau_risque,
            }

            insert_prediction_row(conn, values)
            inserted += 1

        print(f"[EN_COURS] lignes insérées: {inserted}, ignorées: {skipped}")
def execute_sql_script(conn, sql_script: str):
    """
    Exécute un script SQL contenant plusieurs instructions.
    Ignore les lignes de commentaires SQL avant de couper par ';'.
    Cela évite que des commentaires contenant ';' cassent le parser.
    """
    cleaned_lines = []

    for line in sql_script.splitlines():
        stripped = line.strip()

        if not stripped:
            continue

        if stripped.startswith("--"):
            continue

        cleaned_lines.append(line)

    cleaned_script = "\n".join(cleaned_lines)
    statements = cleaned_script.split(";")

    for statement in statements:
        stmt = statement.strip()

        if stmt:
            conn.execute(text(stmt))
def create_grafana_views(conn):
    """
    Crée / met à jour les vues Grafana après le chargement des résultats ML.
    Les vues sont historiques : elles utilisent HOLDOUT et excluent EN_COURS.
    """
    if not os.path.exists(VIEWS_SQL_PATH):
        raise FileNotFoundError(
            f"Fichier SQL des vues introuvable: {VIEWS_SQL_PATH}"
        )
    print("[VUES GRAFANA] Lecture du fichier SQL...")
    with open(VIEWS_SQL_PATH, "r", encoding="utf-8") as f:
        sql_script = f.read()
    print("[VUES GRAFANA] Création / mise à jour des vues...")
    execute_sql_script(conn, sql_script)
    print("[VUES GRAFANA] Vues créées / mises à jour avec succès.")

def main():
    print("Chargement des résultats d'entraînement et des prédictions vers le DW...")
    for stage in STAGES:
        print(f"[dim_modele] {stage}")
        comparaison_df, _, _ = load_stage_files(stage)
        upsert_dim_modele(comparaison_df)
    for stage in STAGES:
        print(f"[fait_prediction HOLDOUT] {stage}")
        _, holdout_df, _ = load_stage_files(stage)
        insert_holdout_predictions(holdout_df)

    for stage in STAGES:
        print(f"[dim_variable] {stage}")
        _, _, importance_df = load_stage_files(stage)
        upsert_dim_variable(importance_df)

    for stage in STAGES:
        print(f"[fait_importance_variable] {stage}")
        _, _, importance_df = load_stage_files(stage)
        insert_fait_importance_variable(importance_df)

    print("[fait_prediction EN_COURS]")
    pred_en_cours_df = load_predictions_en_cours()
    insert_en_cours_predictions(pred_en_cours_df)
    with engine.begin() as conn:
        create_grafana_views(conn)
    print("Chargement terminé avec succès.")

if __name__ == "__main__":
    main()
