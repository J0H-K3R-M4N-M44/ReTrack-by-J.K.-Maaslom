# Reading and formatting the code

Use two spaces for JavaScript, CSS and HTML, and four spaces for PHP. Functions use separate lines for their bodies. Keep related statements together and add a blank line between distinct steps. Comments explain the purpose or a non-obvious rule; they should not repeat each statement.

## Where to look

- `admin/`: PHP page entry points for login, password reset, dashboard and intake.
- `app/`: server-side authentication, database access and business rules.
- `app/intake/rules.php`: intake defaults and validation.
- `app/intake/repository.php`: intake SQL and the transaction that saves related records together.
- `views/`: page markup; `css/` and `js/`: page appearance and browser behavior.
- `demo-prototype/`: a separate demonstration. Its sample records are saved in browser storage, not the PHP database.
- `database/`: database setup scripts. This readability cleanup does not change tables or columns.

## Repeat the formatting

From the repository folder, install the formatting tools locally:

```sh
npm install --no-save --package-lock=false prettier@3.9.10 @prettier/plugin-php@0.26.0
npx prettier --write "**/*.{php,js,css,html}"
npx prettier --check "**/*.{php,js,css,html}"
```

The editor settings are in `.editorconfig`; formatter settings are in `.prettierrc.json`. Third-party QR code source is excluded to keep the supplied library unchanged. SQL scripts retain their existing readable layout. Long HTML strings inside JavaScript are kept intact because changing their whitespace can change the rendered page.

When changing a function, read its callers first, keep its inputs and return values clear, and update any comment that no longer matches its behavior.
