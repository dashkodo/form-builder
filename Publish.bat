@echo off
setlocal

for %%I in ("%~dp0.") do set "SCRIPT_DIR=%%~fI"
set "IMAGE_NAME=dashkodo/forms-builder"
set "VERSION_TAG=%~1"

where docker >nul 2>&1
if errorlevel 1 (
  echo Docker CLI is not available in PATH.
  exit /b 1
)

docker info >nul 2>&1
if errorlevel 1 (
  echo Docker daemon is not running or not accessible.
  exit /b 1
)

echo.
echo Building %IMAGE_NAME%:latest from %SCRIPT_DIR%
docker build -t %IMAGE_NAME%:latest "%SCRIPT_DIR%"
if errorlevel 1 (
  echo Build failed.
  exit /b 1
)

if not "%VERSION_TAG%"=="" (
  echo Tagging %IMAGE_NAME%:%VERSION_TAG%
  docker tag %IMAGE_NAME%:latest %IMAGE_NAME%:%VERSION_TAG%
  if errorlevel 1 (
    echo Failed to create version tag.
    exit /b 1
  )
)

echo.
echo Pushing %IMAGE_NAME%:latest
docker push %IMAGE_NAME%:latest
if errorlevel 1 (
  echo Push failed for latest tag.
  exit /b 1
)

if not "%VERSION_TAG%"=="" (
  echo.
  echo Pushing %IMAGE_NAME%:%VERSION_TAG%
  docker push %IMAGE_NAME%:%VERSION_TAG%
  if errorlevel 1 (
    echo Push failed for version tag.
    exit /b 1
  )
)

echo.
echo Publish completed successfully.
if "%VERSION_TAG%"=="" (
  echo Published tag: latest
) else (
  echo Published tags: latest and %VERSION_TAG%
)
