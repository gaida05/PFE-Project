import json
import os
from typing import Any, Dict
import joblib
import pandas as pd
def ensure_dir(path: str) -> None:
    os.makedirs(path, exist_ok=True)
def ensure_stage_dirs(stage_dir: str) -> None:
    ensure_dir(stage_dir)
    ensure_dir(os.path.join(stage_dir, "models"))
    ensure_dir(os.path.join(stage_dir, "tables"))
    ensure_dir(os.path.join(stage_dir, "charts"))
def save_dataframe(df: pd.DataFrame, path: str) -> None:
    ensure_dir(os.path.dirname(path))
    df.to_csv(path, index=False, encoding="utf-8-sig")
def save_json(data: Dict[str, Any], path: str) -> None:
    ensure_dir(os.path.dirname(path))
    with open(path, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2, default=str)
def save_model(model: Any, path: str) -> None:
    ensure_dir(os.path.dirname(path))
    joblib.dump(model, path)
def load_model(path: str) -> Any:
    return joblib.load(path)