# InPmnt agent notes

## Releases

Always make the zip **downloadable via GitHub**. Local files under `installers/` are a build step, not the handoff.

1. Build with `powershell -File .\build_release.ps1` or `python3 build_release.py`.
2. Tag `vX.Y.Z` and publish a GitHub Release that attaches all four zips.
3. Point README Downloads at the `releases/download/vX.Y.Z/` URLs and verify with `gh release view`.

Pushing a `v*` tag runs `.github/workflows/release.yml`, which builds the zips and creates the release.
