ANALYSE_PREFIX = "analyse__"
LABORANTIN_PREFIX = "laborantin__"
FEATURES_PRECOCE = [
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
]
FEATURES_INTERMEDIAIRE_BASE = [
    "client_ml",
    "secteur_ml",

    "faisabilite_ml",
    "validite_ml",
    "est_conforme_ml",

    "type_echantillon_ml",
    "nom_produit_ml",
    "code_ref_labo_ml",

    "nb_analyses_distinct",
    "nb_laborantins_distinct",

    "nb_analyses",
    "nb_fiches_paillasse",
    "nb_ordres",
    "mois_demande",
    "trimestre_demande",
    "jour_semaine_demande",
    "delai_reception",
    "delai_affectation",
]

FEATURES_AVANCEE_BASE = [
    "client_ml",
    "secteur_ml",

    "faisabilite_ml",
    "validite_ml",
    "est_conforme_ml",

    "type_echantillon_ml",
    "nom_produit_ml",
    "code_ref_labo_ml",

    "nb_analyses_distinct",
    "nb_laborantins_distinct",

    "nb_analyses",
    "nb_fiches_paillasse",
    "nb_ordres",
    "mois_demande",
    "trimestre_demande",
    "jour_semaine_demande",
    "delai_reception",
    "delai_affectation",
    "delai_debut_analyse",
]

FEATURE_SETS = {
    "precoce": FEATURES_PRECOCE,
    "intermediaire": FEATURES_INTERMEDIAIRE_BASE,
    "avancee": FEATURES_AVANCEE_BASE,
}

CATEGORICAL_CANDIDATES = [
    "client_ml",
    "secteur_ml",
    "faisabilite_ml",
    "validite_ml",
    "est_conforme_ml",
    "type_echantillon_ml",
    "nom_produit_ml",
    "code_ref_labo_ml",
]


def _dynamic_columns(df, prefix: str) -> list[str]:
    return sorted([col for col in df.columns if col.startswith(prefix)])


def _deduplicate(seq: list[str]) -> list[str]:
    out = []
    seen = set()
    for item in seq:
        if item not in seen:
            out.append(item)
            seen.add(item)
    return out


def build_feature_sets(df) -> dict[str, list[str]]:
    """
    Builds final feature sets after extraction.
    Multi-hot analysis/laborantin columns are generated dynamically from DW data.
    """
    analyse_cols = _dynamic_columns(df, ANALYSE_PREFIX)
    laborantin_cols = _dynamic_columns(df, LABORANTIN_PREFIX)

    intermediaire = _deduplicate(
        FEATURES_INTERMEDIAIRE_BASE
        + analyse_cols
        + laborantin_cols
    )

    avancee = _deduplicate(
        FEATURES_AVANCEE_BASE
        + analyse_cols
        + laborantin_cols
    )

    return {
        "precoce": FEATURES_PRECOCE,
        "intermediaire": intermediaire,
        "avancee": avancee,
    }
