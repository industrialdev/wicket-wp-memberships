# Import_Controller Class Index

**File:** includes/Import_Controller.php

## Methods

- `__construct()`
- `register_hooks()`
- `import_csv()`
- `process_imported_row($row)`
- `validate_row($row)`
- `log_import_result($result)`
- `get_import_results()`

---

## Method Descriptions

**__construct()**
Initializes the import controller and sets up hooks.

**register_hooks()**
Registers WordPress hooks for the import process.

**import_csv()**
Handles the CSV import process, reading the file and processing each row.

**process_imported_row($row)**
Processes a single row from the imported CSV file.

**validate_row($row)**
Validates a row from the CSV file before importing.

**log_import_result($result)**
Logs the result of an import operation for reporting.

**get_import_results()**
Retrieves the results of the most recent import operation.
