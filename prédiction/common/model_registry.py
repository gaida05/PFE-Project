from sklearn.linear_model import LinearRegression, Ridge
from sklearn.ensemble import RandomForestRegressor
from xgboost import XGBRegressor

from common.config import RANDOM_STATE


def get_model_registry():
    """
    Six candidate models per stage.
    The tree models are regularized to reduce overfitting on a small dataset
    with many one-hot categorical variables.
    """
    return [
        {
            "model_family": "linear",
            "model_name": "LinearRegression",
            "variant": "lr_default",
            "estimator": LinearRegression(),
            "hyperparams": {},
        },
        {
            "model_family": "linear",
            "model_name": "Ridge",
            "variant": "ridge_alpha_1",
            "estimator": Ridge(alpha=1.0, random_state=RANDOM_STATE),
            "hyperparams": {"alpha": 1.0},
        },
        {
            "model_family": "rf",
            "model_name": "RandomForestRegressor",
            "variant": "rf_precoce_500_5_balanced",
            "estimator": RandomForestRegressor(
                n_estimators=500,
                max_depth=5,
                min_samples_split=6,
                min_samples_leaf=3,
                max_features=0.75,
                bootstrap=True,
                random_state=RANDOM_STATE,
                n_jobs=-1,
            ),
            "hyperparams": {
                "n_estimators": 500,
                "max_depth": 5,
                "min_samples_split": 6,
                "min_samples_leaf": 3,
                "max_features": 0.75,
                "bootstrap": True,
                "importance_type": "MDI / mean decrease in impurity",
            }
        },
        {
            "model_family": "rf",
            "model_name": "RandomForestRegressor",
            "variant": "rf_precoce_800_7_robust",
            "estimator": RandomForestRegressor(
                n_estimators=800,
                max_depth=7,
                min_samples_split=8,
                min_samples_leaf=4,
                max_features="sqrt",
                bootstrap=True,
                random_state=RANDOM_STATE,
                n_jobs=-1,
            ),
            "hyperparams": {
                "n_estimators": 800,
                "max_depth": 7,
                "min_samples_split": 8,
                "min_samples_leaf": 4,
                "max_features": "sqrt",
                "bootstrap": True,
                "importance_type": "MDI / mean decrease in impurity",
            }
        },
        {
            "model_family": "xgb",
            "model_name": "XGBoostRegressor",
            "variant": "xgb_stable_200_3",
            "estimator": XGBRegressor(
                n_estimators=200,
                max_depth=3,
                learning_rate=0.04,
                subsample=0.85,
                colsample_bytree=0.85,
                min_child_weight=3,
                reg_alpha=0.10,
                reg_lambda=2.0,
                objective="reg:squarederror",
                random_state=RANDOM_STATE,
                n_jobs=-1,
                importance_type="gain",
            ),
            "hyperparams": {
                "n_estimators": 200,
                "max_depth": 3,
                "learning_rate": 0.04,
                "subsample": 0.85,
                "colsample_bytree": 0.85,
                "min_child_weight": 3,
                "reg_alpha": 0.10,
                "reg_lambda": 2.0,
            },
        },
        {
            "model_family": "xgb",
            "model_name": "XGBoostRegressor",
            "variant": "xgb_balanced_400_4",
            "estimator": XGBRegressor(
                n_estimators=400,
                max_depth=4,
                learning_rate=0.035,
                subsample=0.90,
                colsample_bytree=0.90,
                min_child_weight=2,
                reg_alpha=0.05,
                reg_lambda=1.5,
                objective="reg:squarederror",
                random_state=RANDOM_STATE,
                n_jobs=-1,
                importance_type="gain",
            ),
            "hyperparams": {
                "n_estimators": 400,
                "max_depth": 4,
                "learning_rate": 0.035,
                "subsample": 0.90,
                "colsample_bytree": 0.90,
                "min_child_weight": 2,
                "reg_alpha": 0.05,
                "reg_lambda": 1.5,
                
            },
        },
    ]
