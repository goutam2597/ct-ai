# Ship Package Build Script (Mono Market gate 4.2)

Run from the project root to build the release archives with archive hygiene,
checksums, and the final cross-check. Produces files in `dist/`.

```bash
php docs/build-release.php
```

The script:
1. Creates a clean temp copy of the source (no vendor/, node_modules/, .env,
   storage data, .git, tests, phpstan-ext, .github).
2. Verifies: no local machine paths, no committed secrets, no telemetry.
3. Creates the source archive (zip) and the docs archive (zip).
4. Generates SHA-256 checksums for each.
5. Outputs the final cross-check table for the CHECKLIST.md sign-off.
