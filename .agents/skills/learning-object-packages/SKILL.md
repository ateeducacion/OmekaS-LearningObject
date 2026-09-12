---
name: learning-object-packages
description: "Change SCORM/eXe package ingestion, extraction, thumbnails, rendering, or deletion cleanup."
---

# Learning object packages

## Inspect

Start with `Module.php`, `src/Service/ScormPackageManager.php`, `src/Media/`, and
`test/`. The installed directory and namespace are `LearningObjectAdapter`, despite the repository name.
Trace upload → ingestion → extracted files → renderer → cleanup before changing the format.

## Preserve

- Keep SCORM and eXe package detection distinct; use real fixtures from the test suite.
- Validate ZIP member paths and XML inputs before extraction; malformed packages must fail without
  deleting the previous usable content or writing outside the package directory.
- Preserve launch-file resolution, media ownership, thumbnail generation and deletion cleanup.
  A manifest or screenshot may be absent; do not assume every ZIP has the same shape.
- Register ingesters/renderers in `config/module.config.php`; do not bypass the media API or
  expose arbitrary uploaded paths. This module displays learning objects; it does not implement an LMS gradebook.

## Verify

Run `make lint` and `make test`. Extend the relevant package-manager or workflow test for changed
branches, including a malformed archive and cleanup case. For rendering changes, check an actual
learning object in both the admin media page and a public item page. Report browser checks separately.
