@echo off
setlocal

for %%I in ("%CD%\data.csv") do set "DATA_CSV=%%~fI"
for %%I in ("%CD%\questions.txt") do set "QUESTIONS_TXT=%%~fI"
for %%I in ("%CD%\.instatunnel.env") do set "IT_ENV_FILE=%%~fI"
for %%I in ("%TEMP%\instatunnel-subdomains.txt") do set "IT_SUBDOMAINS_FILE=%%~fI"

if not exist "%IT_ENV_FILE%" (
	echo Missing %IT_ENV_FILE%
	echo Copy .instatunnel.env.example to .instatunnel.env and fill in IT_SUBDOMAIN and IT_API_KEY.
	pause
	exit /b 1
)

if not exist "%DATA_CSV%" (
	echo Creating empty %DATA_CSV%
	type nul > "%DATA_CSV%"
)

rem docker rm -f forms-builder >nul 2>&1
rem docker build -t forms-builder .

set "RUNNING_CONTAINER_ID="
for /f "delims=" %%I in ('docker ps -q --filter "name=^/forms-builder$"') do set "RUNNING_CONTAINER_ID=%%I"
if defined RUNNING_CONTAINER_ID (
	echo Stopping running forms-builder container...
	docker stop forms-builder >nul 2>&1
)

docker rm -f forms-builder >nul 2>&1
docker pull dashkodo/forms-builder:latest
docker run --rm --env-file "%IT_ENV_FILE%" forms-builder sh -lc "it --list --api-key \"$IT_API_KEY\" | sed -n 's/^[[:space:]]*Subdomain: //p'" > "%IT_SUBDOMAINS_FILE%"

set "HAS_ACTIVE_TUNNELS="
for /f "usebackq delims=" %%S in ("%IT_SUBDOMAINS_FILE%") do set "HAS_ACTIVE_TUNNELS=1"

if defined HAS_ACTIVE_TUNNELS (
	echo Active InstaTunnel subdomains:
	type "%IT_SUBDOMAINS_FILE%"
	for /f "usebackq delims=" %%S in ("%IT_SUBDOMAINS_FILE%") do (
		echo.
		choice /C YN /M "Delete tunnel %%S"
		if errorlevel 2 (
			echo Keeping %%S
		) else (
			echo Deleting %%S
			docker run --rm --env-file "%IT_ENV_FILE%" forms-builder sh -lc "it --kill \"%%S\" --api-key \"$IT_API_KEY\" || true"
		)
	)
) else (
	echo No active InstaTunnel subdomains found.
)

del "%IT_SUBDOMAINS_FILE%" >nul 2>&1


docker run -d --name forms-builder --env-file "%IT_ENV_FILE%" -v "%DATA_CSV%:/var/www/html/data.csv" -v "%QUESTIONS_TXT%:/var/www/html/questions.txt" forms-builder
ping -n 5 127.0.0.1 >nul 2>&1
docker logs --tail 50 forms-builder
echo .
echo .
echo !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
echo Closing the command prompt will stop the InstaTunnel and the Docker container.
echo !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
pause
