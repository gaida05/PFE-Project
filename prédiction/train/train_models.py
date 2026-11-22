import json
import os
import numpy as np
import pandas as pd

from copy import deepcopy
from sklearn.base import clone
from sklearn.model_selection import KFold
from sklearn.pipeline import Pipeline

from common.config import (
    TRAIN_DATA_PATH,
    TARGET_COL,
    DATE_COL,
    META_COLUMNS,
    N_SPLITS,
    RANDOM_STATE,
    HOLDOUT_RATIO,
    COMPARAISON_FILE,
    DETAIL_FOLDS_FILE,
    HOLDOUT_METRICS_FILE,
    OOF_PREDICTIONS_FILE,
    HOLDOUT_PREDICTIONS_FILE,
    FEATURE_IMPORTANCE_FILE,
    get_stage_dir,
    get_stage_models_dir,
    get_stage_tables_dir,
)

from common.features import FEATURE_SETS

try:
    from common.features import build_feature_sets as BUILD_FEATURE_SETS
except Exception:
    BUILD_FEATURE_SETS = None

from common.io_utils import ensure_stage_dirs, save_dataframe, save_model
from common.metrics_utils import (
    compute_regression_metrics,
    add_error_columns,
    summarize_cv_metrics,
)
from common.model_registry import get_model_registry
from common.preprocessing import (
    build_preprocessor,
    get_feature_names_from_preprocessor,
)


# =========================================================
# CONFIG TRAINING
# =========================================================

UNKNOWN_TOKENS = [
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
]

# Minimum number of non-missing values for a normal feature.
MIN_NON_MISSING_PER_FEATURE = max(5, N_SPLITS)

# Minimum number of positive occurrences for multi-hot analysis/laborantin columns.
# Example: analyse__XYZ must appear in at least 3 training requests to be kept.
MIN_MULTIHOT_COUNT = 3
MULTIHOT_PREFIXES = ("analyse__", "laborantin__")

FEATURE_IMPORTANCE_DETAIL_FILE = "feature_importance_detail.csv"


# =========================================================
# LOAD DATA
# =========================================================

def load_training_data(path: str) -> pd.DataFrame:
    if not os.path.exists(path):
        raise FileNotFoundError(f"Fichier introuvable: {path}")

    df = pd.read_pickle(path)

    if TARGET_COL not in df.columns:
        raise ValueError(f"La colonne cible '{TARGET_COL}' est absente du dataset.")

    if DATE_COL not in df.columns:
        raise ValueError(f"La colonne date '{DATE_COL}' est absente du dataset.")

    return df


def get_feature_sets_for_dataframe(df: pd.DataFrame) -> dict[str, list[str]]:
    """
    Builds the feature sets.

    If common.features exposes build_feature_sets(df), it is used.
    Otherwise, the static FEATURE_SETS are used and dynamic multi-hot columns
    analyse__* / laborantin__* are added automatically to intermediaire and avancee.

    This keeps the script compatible with both:
    - classic features.py
    - multi-hot features.py
    """
    if callable(BUILD_FEATURE_SETS):
        feature_sets = BUILD_FEATURE_SETS(df)
    else:
        feature_sets = {stage: list(features) for stage, features in FEATURE_SETS.items()}

        analyse_cols = sorted([c for c in df.columns if c.startswith("analyse__")])
        laborantin_cols = sorted([c for c in df.columns if c.startswith("laborantin__")])

        for stage in ["intermediaire", "avancee"]:
            if stage in feature_sets:
                for col in ["nb_analyses_distinct", "nb_laborantins_distinct"]:
                    if col in df.columns and col not in feature_sets[stage]:
                        feature_sets[stage].append(col)

                for col in analyse_cols + laborantin_cols:
                    if col not in feature_sets[stage]:
                        feature_sets[stage].append(col)

        for stage in ["precoce", "intermediaire", "avancee"]:
            if stage in feature_sets and "trimestre_demande" in df.columns:
                if "trimestre_demande" not in feature_sets[stage]:
                    feature_sets[stage].append("trimestre_demande")

    # Safety: keep only existing columns, but report missing later in prepare_stage_dataset.
    return feature_sets


# =========================================================
# CLEANING / DATASET PREPARATION
# =========================================================

def clean_unknown_values(df: pd.DataFrame, cols: list[str]) -> pd.DataFrame:
    """
    Replaces technical unknown strings with np.nan.
    Important: use np.nan, not pd.NA, because sklearn imputers can crash on pd.NA.
    """
    out = df.copy()

    for col in cols:
        if col not in out.columns:
            continue

        if out[col].dtype == "object" or pd.api.types.is_string_dtype(out[col]):
            out[col] = out[col].astype("object")
            out[col] = out[col].replace(UNKNOWN_TOKENS, np.nan)
            out[col] = out[col].where(pd.notna(out[col]), np.nan)

    return out


def prepare_stage_dataset(df: pd.DataFrame, features: list[str]) -> pd.DataFrame:
    """
    Prepares a dataset for one prediction stage.
    Keeps meta columns when present but does not fail if optional meta columns are absent.
    """
    meta_cols_present = [c for c in META_COLUMNS if c in df.columns]

    required_cols = list(dict.fromkeys(meta_cols_present + features + [TARGET_COL]))
    missing_feature_cols = [c for c in features if c not in df.columns]

    if missing_feature_cols:
        raise ValueError(f"Colonnes features manquantes pour ce stade: {missing_feature_cols}")

    temp = df[required_cols].copy()

    temp[DATE_COL] = pd.to_datetime(temp[DATE_COL], errors="coerce")

    temp = temp[temp[TARGET_COL].notna()].copy()
    temp = temp[temp[DATE_COL].notna()].copy()

    # Do not do astype(str), otherwise NaN becomes "nan" and enters OneHotEncoder.
    object_cols = [
        col for col in temp.columns
        if temp[col].dtype == "object" or pd.api.types.is_string_dtype(temp[col])
    ]
    temp = clean_unknown_values(temp, object_cols)

    # Multi-hot columns must be numeric 0/1.
    for col in temp.columns:
        if col.startswith(MULTIHOT_PREFIXES):
            temp[col] = pd.to_numeric(temp[col], errors="coerce").fillna(0).astype(int)

    temp = temp.sort_values(DATE_COL).reset_index(drop=True)

    return temp


def split_train_holdout_temporal(df: pd.DataFrame, holdout_ratio: float):
    """
    Temporal holdout:
    - oldest rows => train_cv
    - newest rows => holdout
    """
    if not 0 < holdout_ratio < 1:
        raise ValueError("HOLDOUT_RATIO doit être entre 0 et 1.")

    n_total = len(df)
    n_holdout = max(1, int(n_total * holdout_ratio))
    n_train = n_total - n_holdout

    if n_train < N_SPLITS:
        raise ValueError(
            "Pas assez de lignes pour faire la séparation temporelle "
            "et la K-fold. Réduis HOLDOUT_RATIO ou N_SPLITS."
        )

    train_cv_df = df.iloc[:n_train].copy()
    holdout_df = df.iloc[n_train:].copy()

    return train_cv_df, holdout_df


# =========================================================
# FEATURE FILTERING
# =========================================================

def filter_sparse_multihot_features(
    train_cv_df: pd.DataFrame,
    features: list[str],
    min_count: int = MIN_MULTIHOT_COUNT
) -> tuple[list[str], list[tuple[str, str]]]:
    """
    Removes rare multi-hot features.

    Example:
    - analyse__A1 kept if it appears in at least min_count demands.
    - analyse__A2 removed if it appears only 1 or 2 times.

    Important:
    Filtering is done only on train_cv, not on holdout, to avoid using future information.
    """
    filtered_features = []
    dropped_features = []

    for feature in features:
        if feature.startswith(MULTIHOT_PREFIXES):
            if feature not in train_cv_df.columns:
                dropped_features.append((feature, "missing_column"))
                continue

            values = pd.to_numeric(train_cv_df[feature], errors="coerce").fillna(0)
            count_active = int((values > 0).sum())

            if count_active >= min_count:
                filtered_features.append(feature)
            else:
                dropped_features.append((feature, f"multi_hot_sparse_{count_active}"))
        else:
            filtered_features.append(feature)

    return filtered_features, dropped_features


def select_active_features(train_cv_df: pd.DataFrame, features: list[str]) -> tuple[list[str], list[tuple[str, str]]]:
    """
    Removes unusable variables for the stage:
    - entirely missing,
    - too sparsely populated,
    - constant after cleaning.

    This selection is also done on train_cv only.
    """
    active_features = []
    dropped_features = []

    for feature in features:
        if feature not in train_cv_df.columns:
            dropped_features.append((feature, "missing_column"))
            continue

        non_missing_count = int(train_cv_df[feature].notna().sum())
        nunique = int(train_cv_df[feature].nunique(dropna=True))

        if non_missing_count == 0:
            dropped_features.append((feature, "all_missing"))
        elif non_missing_count < MIN_NON_MISSING_PER_FEATURE:
            dropped_features.append((feature, f"too_sparse_{non_missing_count}"))
        elif nunique <= 1:
            dropped_features.append((feature, "constant"))
        else:
            active_features.append(feature)

    if not active_features:
        raise ValueError(
            "Toutes les variables du stade sont vides, trop rares ou constantes. "
            "Impossible d'entraîner un modèle."
        )

    return active_features, dropped_features

def summarize_multihot_features(features: list[str]) -> dict[str, int]:
    return {
        "analyse_features": len([f for f in features if f.startswith("analyse__")]),
        "laborantin_features": len([f for f in features if f.startswith("laborantin__")]),
    }

def build_model_pipeline(features: list[str], estimator):
    preprocessor, num_cols, cat_cols = build_preprocessor(features)
    pipeline = Pipeline([
        ("prep", preprocessor),
        ("model", estimator),
    ])
    return pipeline, num_cols, cat_cols

def get_base_feature_name(encoded_feature: str, raw_features: list[str]) -> str:
    if encoded_feature.startswith(("num__", "cat__")):
        encoded_feature = encoded_feature.split("__", 1)[1]

    # Multi-hot variables are already individual binary columns. Keep them as they are.
    if encoded_feature.startswith(MULTIHOT_PREFIXES):
        return encoded_feature

    for raw in sorted(raw_features, key=len, reverse=True):
        if encoded_feature == raw:
            return raw

        if encoded_feature.startswith(raw + "_"):
            return raw

    return encoded_feature


def get_feature_names_safe(preprocessor, num_cols: list[str], cat_cols: list[str]) -> list[str]:
    try:
        return get_feature_names_from_preprocessor(preprocessor, num_cols, cat_cols)
    except Exception:
        feature_names = list(num_cols)

        if cat_cols:
            cat_pipeline = preprocessor.named_transformers_["cat"]
            onehot = cat_pipeline.named_steps["onehot"]

            try:
                cat_feature_names = onehot.get_feature_names_out(cat_cols).tolist()
            except Exception:
                cat_feature_names = onehot.get_feature_names_out().tolist()

            feature_names.extend(cat_feature_names)

        return feature_names


def extract_feature_importance_detail(fitted_pipeline, num_cols, cat_cols) -> pd.DataFrame:
    model = fitted_pipeline.named_steps["model"]

    if not hasattr(model, "feature_importances_"):
        return pd.DataFrame(columns=["feature_detail", "importance"])

    preprocessor = fitted_pipeline.named_steps["prep"]
    feature_names = get_feature_names_safe(preprocessor, num_cols, cat_cols)
    importances = model.feature_importances_

    if len(feature_names) != len(importances):
        feature_names = [f"feature_{i}" for i in range(len(importances))]

    return (
        pd.DataFrame({
            "feature_detail": feature_names,
            "importance": importances,
        })
        .sort_values("importance", ascending=False)
        .reset_index(drop=True)
    )


def extract_feature_importance(fitted_pipeline, num_cols, cat_cols) -> pd.DataFrame:
    detail_df = extract_feature_importance_detail(fitted_pipeline, num_cols, cat_cols)

    if detail_df.empty:
        return pd.DataFrame(columns=["feature", "importance"])

    raw_features = list(num_cols) + list(cat_cols)

    detail_df["feature"] = detail_df["feature_detail"].apply(
        lambda x: get_base_feature_name(str(x), raw_features)
    )

    grouped_importance_df = (
        detail_df
        .groupby("feature", as_index=False)["importance"]
        .sum()
        .sort_values("importance", ascending=False)
        .reset_index(drop=True)
    )

    return grouped_importance_df


def save_feature_importance_outputs(
    importance_df: pd.DataFrame,
    importance_detail_df: pd.DataFrame,
    stage: str
):
    tables_dir = get_stage_tables_dir(stage)

    csv_path = os.path.join(tables_dir, FEATURE_IMPORTANCE_FILE)
    detail_path = os.path.join(tables_dir, FEATURE_IMPORTANCE_DETAIL_FILE)

    save_dataframe(importance_df, csv_path)
    save_dataframe(importance_detail_df, detail_path)

def fit_evaluate_candidate(
    stage: str,
    df_stage: pd.DataFrame,
    features: list[str],
    model_cfg: dict
):
    model_name = model_cfg["model_name"]
    model_family = model_cfg["model_family"]
    variant = model_cfg["variant"]
    estimator = model_cfg["estimator"]
    hyperparams = model_cfg["hyperparams"]

    train_cv_df, holdout_df = split_train_holdout_temporal(df_stage, HOLDOUT_RATIO)

    X_train_full = train_cv_df[features].copy()
    y_train_full = train_cv_df[TARGET_COL].copy()

    X_holdout = holdout_df[features].copy()
    y_holdout = holdout_df[TARGET_COL].copy()

    meta_cols_train = [c for c in META_COLUMNS if c in train_cv_df.columns]
    meta_cols_holdout = [c for c in META_COLUMNS if c in holdout_df.columns]

    meta_train = train_cv_df[meta_cols_train].copy()
    meta_holdout = holdout_df[meta_cols_holdout].copy()

    kf = KFold(n_splits=N_SPLITS, shuffle=True, random_state=RANDOM_STATE)
    
    fold_metrics_rows = []
    oof_rows = []
    for fold_idx, (train_idx, valid_idx) in enumerate(kf.split(X_train_full), start=1):
        X_train = X_train_full.iloc[train_idx].copy()
        y_train = y_train_full.iloc[train_idx].copy()
        X_valid = X_train_full.iloc[valid_idx].copy()
        y_valid = y_train_full.iloc[valid_idx].copy()
        meta_valid = meta_train.iloc[valid_idx].copy()
        pipeline, _, _ = build_model_pipeline(features, clone(estimator))
        pipeline.fit(X_train, y_train)
        y_pred_valid = pipeline.predict(X_valid)
        fold_metrics = compute_regression_metrics(y_valid, y_pred_valid)
        fold_metrics["fold"] = fold_idx
        fold_metrics["stage"] = stage
        fold_metrics["model_family"] = model_family
        fold_metrics["model_name"] = model_name
        fold_metrics["variant"] = variant
        fold_metrics_rows.append(fold_metrics)
        pred_fold_df = meta_valid.copy()
        pred_fold_df["stage"] = stage
        pred_fold_df["model_family"] = model_family
        pred_fold_df["model_name"] = model_name
        pred_fold_df["variant"] = variant
        pred_fold_df["fold"] = fold_idx
        pred_fold_df["y_true"] = y_valid.values
        pred_fold_df["y_pred"] = y_pred_valid
        pred_fold_df = add_error_columns(pred_fold_df)
        oof_rows.append(pred_fold_df)

    detail_folds_df = pd.DataFrame(fold_metrics_rows)
    oof_predictions_df = pd.concat(oof_rows, ignore_index=True)

    cv_summary = summarize_cv_metrics(detail_folds_df)

    final_train_pipeline, num_cols, cat_cols = build_model_pipeline(features, clone(estimator))
    final_train_pipeline.fit(X_train_full, y_train_full)

    y_holdout_pred = final_train_pipeline.predict(X_holdout)

    holdout_metrics = compute_regression_metrics(y_holdout, y_holdout_pred)

    holdout_metrics_row = {
        "stage": stage,
        "model_family": model_family,
        "model_name": model_name,
        "variant": variant,
        **holdout_metrics,
    }

    holdout_metrics_df = pd.DataFrame([holdout_metrics_row])

    holdout_predictions_df = meta_holdout.copy()
    holdout_predictions_df["stage"] = stage
    holdout_predictions_df["model_family"] = model_family
    holdout_predictions_df["model_name"] = model_name
    holdout_predictions_df["variant"] = variant
    holdout_predictions_df["y_true"] = y_holdout.values
    holdout_predictions_df["y_pred"] = y_holdout_pred

    holdout_predictions_df = add_error_columns(holdout_predictions_df)

    importance_df = extract_feature_importance(final_train_pipeline, num_cols, cat_cols)
    importance_detail_df = extract_feature_importance_detail(final_train_pipeline, num_cols, cat_cols)

    if not importance_df.empty:
        importance_df["stage"] = stage
        importance_df["model_family"] = model_family
        importance_df["model_name"] = model_name
        importance_df["variant"] = variant

    if not importance_detail_df.empty:
        importance_detail_df["stage"] = stage
        importance_detail_df["model_family"] = model_family
        importance_detail_df["model_name"] = model_name
        importance_detail_df["variant"] = variant

    comparison_row = {
        "stage": stage,
        "model_family": model_family,
        "model_name": model_name,
        "variant": variant,
        "hyperparams": json.dumps(hyperparams, ensure_ascii=False),
        "n_train_cv": len(train_cv_df),
        "n_holdout": len(holdout_df),
        **cv_summary,
        "holdout_MAE": holdout_metrics["MAE"],
        "holdout_RMSE": holdout_metrics["RMSE"],
        "holdout_R2": holdout_metrics["R2"],
    }

    return {
        "comparison_row": comparison_row,
        "detail_folds_df": detail_folds_df,
        "oof_predictions_df": oof_predictions_df,
        "holdout_metrics_df": holdout_metrics_df,
        "holdout_predictions_df": holdout_predictions_df,
        "importance_df": importance_df,
        "importance_detail_df": importance_detail_df,
        "trained_pipeline_train_only": final_train_pipeline,
    }


def refit_best_model_on_full_stage(
    df_stage: pd.DataFrame,
    features: list[str],
    best_model_cfg: dict
):
    X_all = df_stage[features].copy()
    y_all = df_stage[TARGET_COL].copy()

    best_pipeline, _, _ = build_model_pipeline(features, clone(best_model_cfg["estimator"]))
    best_pipeline.fit(X_all, y_all)

    return best_pipeline


def run_stage_training(stage: str, df: pd.DataFrame, features: list[str]):
    print(f"\n===== STADE: {stage.upper()} =====")

    stage_dir = get_stage_dir(stage)
    models_dir = get_stage_models_dir(stage)
    tables_dir = get_stage_tables_dir(stage)

    ensure_stage_dirs(stage_dir)

    df_stage = prepare_stage_dataset(df, features)

    # Selection must use train_cv only to avoid future leakage from holdout.
    train_cv_for_selection, _ = split_train_holdout_temporal(df_stage, HOLDOUT_RATIO)

    # 1. Drop rare multi-hot analysis/laborantin features.
    features_after_sparse_filter, sparse_dropped_features = filter_sparse_multihot_features(
        train_cv_for_selection,
        features,
        min_count=MIN_MULTIHOT_COUNT
    )

    # 2. Drop empty, too sparse, or constant features.
    active_features, dropped_features = select_active_features(
        train_cv_for_selection,
        features_after_sparse_filter
    )

    dropped_features = sparse_dropped_features + dropped_features

    print(f"Variables initiales: {len(features)}")
    print(f"Variables utilisées: {len(active_features)}")
    print(f"Lignes entraînables pour {stage}: {len(df_stage)}")

    initial_summary = summarize_multihot_features(features)
    active_summary = summarize_multihot_features(active_features)

    print(
        "Multi-hot initiales: "
        f"analyse={initial_summary['analyse_features']}, "
        f"laborantin={initial_summary['laborantin_features']}"
    )
    print(
        "Multi-hot utilisées: "
        f"analyse={active_summary['analyse_features']}, "
        f"laborantin={active_summary['laborantin_features']}"
    )

    if dropped_features:
        print("Variables supprimées pour ce stade:")

        max_to_print = 60
        for feature, reason in dropped_features[:max_to_print]:
            print(f"  - {feature}: {reason}")

        if len(dropped_features) > max_to_print:
            print(f"  ... {len(dropped_features) - max_to_print} autres variables supprimées")

    model_registry = get_model_registry()

    comparison_rows = []
    all_folds = []
    all_oof = []
    all_holdout_metrics = []
    all_holdout_predictions = []
    all_importances = []
    all_importance_details = []
    trained_candidates = []

    for model_cfg in model_registry:
        print(
            f"Stage={stage} | "
            f"Model={model_cfg['model_name']} | "
            f"Variant={model_cfg['variant']}"
        )

        result = fit_evaluate_candidate(stage, df_stage, active_features, model_cfg)

        comparison_rows.append(result["comparison_row"])
        all_folds.append(result["detail_folds_df"])
        all_oof.append(result["oof_predictions_df"])
        all_holdout_metrics.append(result["holdout_metrics_df"])
        all_holdout_predictions.append(result["holdout_predictions_df"])

        if not result["importance_df"].empty:
            all_importances.append(result["importance_df"])

        if not result["importance_detail_df"].empty:
            all_importance_details.append(result["importance_detail_df"])

        trained_candidates.append({
            "model_cfg": deepcopy(model_cfg),
            "comparison_row": result["comparison_row"],
        })

    comparaison_df = (
        pd.DataFrame(comparison_rows)
        .sort_values(
            by=["holdout_MAE", "MAE_mean", "holdout_RMSE"],
            ascending=[True, True, True]
        )
        .reset_index(drop=True)
    )

    detail_folds_df = pd.concat(all_folds, ignore_index=True)
    oof_predictions_df = pd.concat(all_oof, ignore_index=True)
    holdout_metrics_df = pd.concat(all_holdout_metrics, ignore_index=True)
    holdout_predictions_df = pd.concat(all_holdout_predictions, ignore_index=True)

    if all_importances:
        importance_df = pd.concat(all_importances, ignore_index=True)
    else:
        importance_df = pd.DataFrame(columns=[
            "feature",
            "importance",
            "stage",
            "model_family",
            "model_name",
            "variant",
        ])

    if all_importance_details:
        importance_detail_df = pd.concat(all_importance_details, ignore_index=True)
    else:
        importance_detail_df = pd.DataFrame(columns=[
            "feature_detail",
            "importance",
            "stage",
            "model_family",
            "model_name",
            "variant",
        ])

    save_dataframe(comparaison_df, os.path.join(tables_dir, COMPARAISON_FILE))
    save_dataframe(detail_folds_df, os.path.join(tables_dir, DETAIL_FOLDS_FILE))
    save_dataframe(holdout_metrics_df, os.path.join(tables_dir, HOLDOUT_METRICS_FILE))
    save_dataframe(oof_predictions_df, os.path.join(tables_dir, OOF_PREDICTIONS_FILE))
    save_dataframe(holdout_predictions_df, os.path.join(tables_dir, HOLDOUT_PREDICTIONS_FILE))
    save_feature_importance_outputs(importance_df, importance_detail_df, stage)

    best_row = comparaison_df.iloc[0].to_dict()
    best_variant = best_row["variant"]
    best_model_name = best_row["model_name"]

    best_cfg = None

    for candidate in trained_candidates:
        cfg = candidate["model_cfg"]

        if cfg["variant"] == best_variant and cfg["model_name"] == best_model_name:
            best_cfg = cfg
            break

    if best_cfg is None:
        raise RuntimeError("Impossible de retrouver la configuration du meilleur modèle.")

    print(
        f"BEST for {stage}: {best_cfg['model_name']} | {best_cfg['variant']} "
        f"| holdout_MAE={best_row['holdout_MAE']:.4f}"
    )

    best_full_pipeline = refit_best_model_on_full_stage(
        df_stage,
        active_features,
        best_cfg
    )

    best_model_path = os.path.join(models_dir, "best_model.pkl")
    save_model(best_full_pipeline, best_model_path)

    explicit_model_name = f"{best_cfg['model_name']}_{best_cfg['variant']}.pkl"
    explicit_model_path = os.path.join(models_dir, explicit_model_name)
    save_model(best_full_pipeline, explicit_model_path)

    best_summary_df = pd.DataFrame([best_row])
    save_dataframe(best_summary_df, os.path.join(tables_dir, "best_model_summary.csv"))

    active_features_df = pd.DataFrame({
        "stage": stage,
        "feature": active_features,
    })
    save_dataframe(active_features_df, os.path.join(tables_dir, "active_features.csv"))

    dropped_features_df = pd.DataFrame(
        dropped_features,
        columns=["feature", "reason"]
    )
    dropped_features_df.insert(0, "stage", stage)
    save_dataframe(dropped_features_df, os.path.join(tables_dir, "dropped_features.csv"))
def main():
    print("Chargement du dataset d'entraînement...")
    df = load_training_data(TRAIN_DATA_PATH)
    feature_sets = get_feature_sets_for_dataframe(df)
    for stage, features in feature_sets.items():
        run_stage_training(stage, df, features)
    print("\nEntraînement terminé pour tous les stades.")
if __name__ == "__main__":
    main()






   