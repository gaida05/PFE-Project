import pandas as pd
from sqlalchemy import create_engine

csv_path = r"C:\Users\majda\Downloads\an.csv"

df = pd.read_csv(csv_path)   

engine = create_engine("mysql+pymysql://root:@localhost/temp?charset=utf8mb4")

df.to_sql("analyse_csv_data", con=engine, if_exists="replace", index=False)

print(df.head())
print("Rows imported:", len(df))
print("Table created: analyse_csv_data")