from typing import Dict
import numpy as np
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
def compute_regression_metrics(y_true, y_pred) -> Dict[str, float]:
    return {
        "MAE": float(mean_absolute_error(y_true, y_pred)),
        "RMSE": float(np.sqrt(mean_squared_error(y_true, y_pred))),
        "R2": float(r2_score(y_true, y_pred)),
    }

def add_error_columns(df, y_true_col: str = "y_true", y_pred_col: str = "y_pred"):
    out = df.copy()
    out["erreur_signee"] = out[y_pred_col] - out[y_true_col]
    out["erreur_absolue"] = np.abs(out["erreur_signee"])
    return out

def summarize_cv_metrics(metrics_df):
    return {
        "MAE_mean": float(metrics_df["MAE"].mean()),
        "MAE_std": float(metrics_df["MAE"].std(ddof=0)),
        "RMSE_mean": float(metrics_df["RMSE"].mean()),
        "RMSE_std": float(metrics_df["RMSE"].std(ddof=0)),
        "R2_mean": float(metrics_df["R2"].mean()),
        "R2_std": float(metrics_df["R2"].std(ddof=0)),
    }