Replace these files in your import folder:

clean_helpers.php
import.php
import_data.php
import_file.php

csv_helpers.php and transform_helpers.php are included only for completeness and were not changed.
Do not replace import_core.php; the import order is unchanged.

Run import_core.php after replacement so old non-null wrong dates are cleared by the reset and re-import.
