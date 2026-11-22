from typing import List, Tuple
import numpy as np
import pandas as pd

from sklearn.compose import ColumnTransformer
from sklearn.impute import SimpleImputer
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

from .features import CATEGORICAL_CANDIDATES
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
def split_feature_types(features: List[str]) -> Tuple[List[str], List[str]]:
    cat_cols = [col for col in features if col in CATEGORICAL_CANDIDATES]
    num_cols = [col for col in features if col not in cat_cols]
    return num_cols, cat_cols

def clean_categorical_values(df: pd.DataFrame, cat_cols: List[str]) -> pd.DataFrame:
    out = df.copy()
    for col in cat_cols:
        if col in out.columns:
            out[col] = out[col].astype("object")
            out[col] = out[col].replace(UNKNOWN_TOKENS, np.nan)
            out[col] = out[col].where(pd.notna(out[col]), np.nan)
    return out

def build_preprocessor(features: List[str]) -> Tuple[ColumnTransformer, List[str], List[str]]:

    num_cols, cat_cols = split_feature_types(features)
    numeric_pipeline = Pipeline([
        ("imputer", SimpleImputer(strategy="median")),
    ])
    categorical_pipeline = Pipeline([
        ("imputer", SimpleImputer(strategy="constant", fill_value="__MISSING__")),
        ("onehot", OneHotEncoder(handle_unknown="ignore")),
    ])
    preprocessor = ColumnTransformer([
        ("num", numeric_pipeline, num_cols),
        ("cat", categorical_pipeline, cat_cols),
    ])
    return preprocessor, num_cols, cat_cols

def prepare_dataframe_for_modeling(
    df: pd.DataFrame,
    features: List[str],
    target_col: str
) -> pd.DataFrame:
    required_cols = features + [target_col]
    missing_cols = [c for c in required_cols if c not in df.columns]

    if missing_cols:
        raise ValueError(f"Colonnes manquantes dans le dataset: {missing_cols}")

    temp = df[required_cols].copy()

    num_cols, cat_cols = split_feature_types(features)
    temp = clean_categorical_values(temp, cat_cols)

    temp = temp[temp[target_col].notna()].copy()

    return temp


def get_feature_names_from_preprocessor(
    preprocessor,
    num_cols: List[str],
    cat_cols: List[str]
) -> List[str]:
    feature_names = list(num_cols)

    if cat_cols:
        cat_pipeline = preprocessor.named_transformers_["cat"]
        onehot = cat_pipeline.named_steps["onehot"]
        cat_feature_names = onehot.get_feature_names_out(cat_cols).tolist()
        feature_names.extend(cat_feature_names)

    return feature_names




