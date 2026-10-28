# Code style for RepairTrack

This project is written to be easy to read and change during a student project. Keep code direct
and explain the parts that are not obvious.

## Basic rules

- Use four spaces for each indentation level.
- Give variables and functions names that explain what they hold or do.
- Keep each function focused on one job. Split it only when that makes the steps easier to follow.
- Use comments to explain why a rule exists or what a tricky step protects.
- Prefer ordinary `if` statements and small loops when they are clearer than a compact expression.
- Keep database queries near the PHP function that uses them.
- Use prepared statements for values sent to SQL, and escape text before putting it in HTML.
- Make one small change at a time so it is easier to review and undo.

## File roles

| Folder            | What belongs there                                            |
| ----------------- | ------------------------------------------------------------- |
| `admin/`          | Pages and form actions used by staff                          |
| `app/`            | Database, login, and reusable application functions           |
| `config/`         | Local database settings; do not commit real passwords         |
| `css/` and `js/`  | Page appearance and browser interactions                      |
| `database/`       | Database creation and update scripts                          |
| `views/`          | HTML forms and page sections rendered by PHP                  |
| `demo-prototype/` | Clickable browser-only prototype                              |
| `docs/`           | Wireframes, workflows, maintenance notes, and coding guidance |

The `demo-prototype/vendor/` folder contains third-party code. Leave it as provided by its author.

## Formatting

The root `.editorconfig` and `.prettierrc.json` describe the shared spacing and line-width rules.
When changing a file, keep its existing language style and format only the files you touched. The
SQL schema may be reformatted for readability, but changing its tables, columns, keys, or rules is a
database change and should be reviewed separately.
