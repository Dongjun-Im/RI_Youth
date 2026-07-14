@echo off
REM 테스트 실행 (XAMPP PHP 사용). 이 파일을 더블클릭하거나 명령창에서 실행하세요.
setlocal
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php
"%PHP%" "%~dp0run_tests.php"
echo.
pause
