import os
BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
DATA_DIR = os.path.join(BASE_DIR, "data")
OUTPUTS_DIR = os.path.join(BASE_DIR, "outputs")
EXTRACT_DIR = os.path.join(BASE_DIR, "extract")
TRAIN_DIR = os.path.join(BASE_DIR, "train")
PREDICT_DIR = os.path.join(BASE_DIR, "predict")
PRECOCE_DIR = os.path.join(OUTPUTS_DIR, "precoce")
INTERMEDIAIRE_DIR = os.path.join(OUTPUTS_DIR, "intermediaire")
AVANCEE_DIR = os.path.join(OUTPUTS_DIR, "avancee")
STAGE_DIRS = {
    "precoce": PRECOCE_DIR,
    "intermediaire": INTERMEDIAIRE_DIR,
    "avancee": AVANCEE_DIR,
}
TRAIN_DATA_PATH = os.path.join(DATA_DIR, "dataset_train.pkl")
PREDICT_DATA_PATH = os.path.join(DATA_DIR, "dataset_predict.pkl")
DB_URL = "mysql+pymysql://root:@localhost:3306/dw_labo"
TARGET_COL = "target_delai_jours"
DATE_COL = "date_demande"
ID_COL = "id_demande"
CLIENT_SK_COL = "id_client_sk"
SECTEUR_SK_COL = "id_secteur_sk"
STATUT_SK_COL = "id_statut_sk"
META_COLUMNS = [
    ID_COL,
    CLIENT_SK_COL,
    SECTEUR_SK_COL,
    STATUT_SK_COL,
    DATE_COL,
]
N_SPLITS = 5
RANDOM_STATE = 42
HOLDOUT_RATIO = 0.20
RETARD_THRESHOLD_JOURS = 0.0
COMPARAISON_FILE = "comparaison_modeles.csv"
DETAIL_FOLDS_FILE = "detail_folds.csv"
HOLDOUT_METRICS_FILE = "holdout_metrics.csv"
OOF_PREDICTIONS_FILE = "predictions_oof.csv"
HOLDOUT_PREDICTIONS_FILE = "predictions_holdout.csv"
FEATURE_IMPORTANCE_FILE = "feature_importance.csv"
PREDICTION_INPUTS_FILE = "prediction_inputs.csv"
FEATURE_IMPORTANCE_CHART = "feature_importance_top20.png"
def get_stage_dir(stage: str) -> str:
    return STAGE_DIRS[stage]
def get_stage_models_dir(stage: str) -> str:
    return os.path.join(get_stage_dir(stage), "models")
def get_stage_tables_dir(stage: str) -> str:
    return os.path.join(get_stage_dir(stage), "tables")
def get_stage_charts_dir(stage: str) -> str:
    return os.path.join(get_stage_dir(stage), "charts")

