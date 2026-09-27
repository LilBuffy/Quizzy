# QUIZZY

Isang fucking online quiz platform built with **PHP, MySQL, HTML, CSS, and Vanilla JavaScript**, made for classrooms, competitions, and interactive learning. Teachers gumawa ng quizzes, participants sasali gamit ang name at quiz code, tapos magsisimula na ang academic fucking bloodbath sa leaderboard. Simple idea lang dapat, pero syempre kailangan may timer, powerups, rankings, security, at database para mas masaya ang suffering.

**Project Status:** ABANDONED / GITHUB CEMETERY

Educational project ko lang 'to at hindi na maintained. Tapos na ang quizzes, umalis na ang participants, frozen na ang leaderboard, at yung database pwede nang magpahinga. Вечная память, QUIZZY.

### What This Shit Can Do

QUIZZY lets teachers **register, login, create, edit, and delete quizzes**, manage questions, set answers and points, generate quiz codes, configure timers, enable or disable powerups, start and close quizzes, monitor participants, and view leaderboard and quiz statistics. Basically, teacher ka ngayon pero may sariling fucking quiz empire.

Participants don't need an account. They simply enter their **name and quiz code**, answer questions, track their progress, deal with the countdown timer, use available powerups, and watch their score climb or fucking collapse in real time. No registration. No password. Enter code, then GO FUCKING FIGHT.

### Powerups

May iba't ibang powerups para dagdag gulo sa quiz tulad ng **Double Points, Fifty Fifty, Time Boost, Shield, and Score Boost**.

Server side validated ang scores and powerups, so hindi basta basta makakapag DevTools tapos biglang 999999 points. Nice try, gagu.

### Leaderboard

May **live rankings, participant names, scores, correct answers, ranking, tie handling, and competitive scoring**. Basically, ginawa ko ang quiz para matuto ang students, pero somehow naging Hunger Games ang leaderboard.

### Security

The system includes **secure teacher authentication, password hashing, PDO prepared statements, CSRF protection, XSS protection, IDOR protection, server side scoring, server side timer validation, quiz code protection, duplicate submission prevention, rate limiting, and session security**.

Basically, sinubukan kong siguraduhin na hindi basta basta masisira ang quiz dahil lang may isang participant na gustong maging fucking Einstein gamit ang DevTools.

### 24 Hour Data Cleanup

Temporary participant data, sessions, answers, and results are automatically deleted after **24 hours**. Teacher quizzes and questions stay until manually deleted.

Less useless data. Less database govno.

### Tech Stack

**HTML, CSS, Vanilla JavaScript, PHP, MySQL / MariaDB, and XAMPP.**

PHP handles the backend logic, MySQL or MariaDB handles the data, while HTML, CSS, and JavaScript handle the actual quiz interface and interactions. Simple stack lang. Walang unnecessary framework, walang 900 dependencies, just enough technology para makapag quiz at mag iyakan sa leaderboard.

Quizzy: mag aral ka, lumaban ka, tapos mag iyakan sa leaderboard.