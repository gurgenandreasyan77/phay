# Phay: запуск локально и в продакшене

Этот документ про репозиторий **phay** (API + админка). Сайт Servio живёт в
своём репозитории `servio`, у него свой `docs/DEPLOY.md`. Подготовка сервера
(Docker, firewall, nginx, сертификаты) общая для обоих проектов и описана
здесь, в разделе 4.

Содержание:

1. [Что где работает](#1-что-где-работает)
2. [Файлы окружения](#2-файлы-окружения)
3. [Команды make](#3-команды-make)
4. [Подготовка сервера (один раз, общая для phay и servio)](#4-подготовка-сервера)
5. [Первый запуск Phay в продакшене](#5-первый-запуск-phay-в-продакшене)
6. [Подключение сайта Servio](#6-подключение-сайта-servio)
7. [Переменные .env.prod: что значит и где взять](#7-переменные-envprod)
8. [Бэкапы и восстановление](#8-бэкапы-и-восстановление)
9. [Обновление](#9-обновление)
10. [Откат](#10-откат)
11. [Перенос данных с локальной машины или старого сервера](#11-перенос-данных)
12. [Локальная разработка](#12-локальная-разработка)
13. [Если что-то не работает](#13-если-что-то-не-работает)

---

## 1. Что где работает

| Адрес | Что | Откуда |
| --- | --- | --- |
| `https://app.phay.am` | Админка (все бизнесы и платформа) | phay, контейнер `admin`, 127.0.0.1:3001 |
| `https://api.phay.am` | API: публичный для сайтов (`/api/v1/*`), админский (`/api/v1/admin/*`), файлы (`/storage/*`) | phay, контейнер `api` (nginx) → `backend` (php-fpm), 127.0.0.1:8080 |
| `https://phay.am` | Пока редирект 302 на `app.phay.am` | nginx на хосте |
| `https://servio.am` | Сайт клининга | servio, контейнер `frontend`, 127.0.0.1:3000 |
| `admin.servio.am` | Старый адрес админки, редирект 301 на `app.phay.am` | nginx на хосте |

**Про «админка на phay.am».** Набрав `phay.am`, человек сразу попадает в
админку: nginx отправляет его на `app.phay.am`. Сама админка работает на
`app.phay.am`, и это сделано специально: корень `phay.am` остаётся
свободным для будущего лендинга продукта (цены, регистрация), а ссылки в
письмах приглашений и Google OAuth уже привязаны к `app.phay.am`. Если
лендинг не нужен никогда и хочется админку прямо на `phay.am`, надо поменять
`ADMIN_URL` и `GOOGLE_REDIRECT_URI` в `.env.prod`, redirect URI в Google
Cloud и server-блоки в `deploy/phay.nginx.conf`. Я рекомендую оставить как
есть.

```
браузер ─► nginx на хосте (:80/:443, TLS) ─┬─► 127.0.0.1:3001  phay admin ──► api (внутри Docker)
                                          ├─► 127.0.0.1:8080  phay api ──► backend ──► postgres
                                          └─► 127.0.0.1:3000  servio frontend ──► https://api.phay.am
```

Оба проекта лежат рядом и не зависят друг от друга на уровне Docker:

```
/var/www/projects/
├── phay/     compose-проект phay-prod: postgres, backend, scheduler, api, admin
└── servio/   compose-проект servio-prod: frontend
```

Порты 80 и 443 держит только nginx, установленный на сам сервер. Контейнеры
публикуют порты только на `127.0.0.1`, поэтому снаружи до них не достучаться
в обход nginx.

Контейнеры продакшена Phay:

| Сервис | Что делает |
| --- | --- |
| `postgres` | База PostgreSQL 17. Порт наружу не публикуется |
| `backend` | Laravel на php-fpm |
| `scheduler` | Тот же образ, `php artisan schedule:work`: ежедневная рассылка предупреждений о подписке (10:00 по Еревану) и очистка просроченных сессий админки |
| `api` | nginx внутри Docker: отдаёт `/storage/*` и передаёт PHP в `backend` |
| `admin` | Next.js админка |

Очередей (queue worker) нет: ни одна задача сейчас не ставится в очередь,
обновление кеша сайтов отправляется сразу после ответа.

## 2. Файлы окружения

| Файл | Где | Для чего | В git |
| --- | --- | --- | --- |
| `.env.example` → `.env` | локально | Настройки compose: имя проекта, порты, логин/пароль локальной базы | только пример |
| `api/.env.example` → `api/.env` | локально | Все настройки Laravel для разработки: APP_KEY, почта в лог, ключ ИИ, Google | только пример |
| `admin/.env.example` | только без Docker | `NEXT_INTERNAL_API_URL`, если запускать админку через `npm run dev` | только пример |
| `.env.prod.example` → `.env.prod` | сервер | Всё для продакшена в одном файле: compose, база, Laravel, почта, SMS, ИИ, Google | только пример |

Правила:

- Локальные команды (`make local-*`) читают только `.env` и `api/.env`.
- Продакшен-команды (`make prod-*`) читают только `.env.prod`
  (`docker compose --env-file .env.prod -f compose.prod.yaml`). Локальный
  `.env` в продакшене не читается вообще.
- У продакшена другое имя compose-проекта (`phay-prod`), поэтому даже если
  запустить prod-стек на своём Mac, он создаст свои тома и не тронет
  локальную базу `phay_postgres_data`.
- Все ключи из `.env.prod` попадают в Laravel (`env_file` в
  `compose.prod.yaml`). Любую настройку Laravel из `api/.env.example` можно
  добавить в `.env.prod`, и она заработает после `make prod-up`.
- `.env`, `api/.env` и `.env.prod` в `.gitignore` и в `.dockerignore`: в git
  и в образы они не попадают.

## 3. Команды make

`make` без аргументов показывает список всех команд с описаниями.

| Действие | Локально | Продакшен |
| --- | --- | --- |
| Первый запуск | `make local-setup` | `make prod-init`, затем `prod-build prod-up prod-migrate` |
| Собрать образы | `make local-build` | `make prod-build` |
| Запустить | `make local-up` | `make prod-up` |
| Остановить | `make local-down` | `make prod-down` |
| Перезапустить | `make local-restart [s=backend]` | `make prod-restart [s=backend]` |
| Логи контейнеров | `make local-logs [s=backend]` | `make prod-logs [s=admin]` |
| Лог Laravel | `storage/logs` в `api/` | `make prod-logs-app` |
| Состояние | `make local-ps` | `make prod-ps` |
| Миграции | `make local-migrate` | `make prod-migrate` |
| Статус миграций | `make local-migrate-status` | `make prod-migrate-status` |
| Первый супер-админ | `make local-bootstrap email=… name="…"` | `make prod-bootstrap email=… name="…"` |
| Ключи сайтов | `make local-site-keys` | `make prod-site-keys` |
| artisan | `make local-artisan cmd="…"` | `make prod-artisan cmd="…"` |
| Shell | `make local-shell`, `make local-shell-admin` | `make prod-shell` |
| Бэкап | `make local-backup` (только база) | `make prod-backup` (база + файлы) |
| Восстановление | `make local-restore file=…` | `make prod-restore file=… confirm=yes`, `make prod-restore-storage file=…` |
| Тесты | `make local-test` | нет |
| Демо-данные Servio | `make local-seed-servio` | `make prod-artisan cmd="db:seed --class=ServioDemoSeeder --force"` |
| Обновление | `git pull` + `make local-migrate` | `make prod-deploy` |

Старые команды → новые: `setup` → `local-setup`, `up` → `local-up`,
`down` → `local-down`, `restart` → `local-restart`, `logs` → `local-logs`,
`migrate` → `local-migrate`, `seed-servio` → `local-seed-servio`,
`site-keys` → `local-site-keys`, `test` → `local-test`, `artisan` →
`local-artisan`, `backup-db` → `local-backup`, `restore-db` → `local-restore`.
`build`, `seed`, `fresh`, `key-generate` убраны (вместо них `local-build`,
`local-fresh`, `local-key`).

## 4. Подготовка сервера

Делается один раз на сервер, для phay и servio вместе.

### 4.1. Что нужно

- VPS с **Ubuntu 24.04 LTS** (22.04 тоже подойдёт).
- Минимум **2 vCPU, 4 ГБ RAM, 40 ГБ диска**. Сборка двух Next.js-приложений
  (`next build`) съедает до 2 ГБ памяти; на 2 ГБ RAM нужен swap (ниже).
- Доступ по SSH с ключом.
- Домены `phay.am` и `servio.am` в панели регистратора.

### 4.2. DNS

У регистратора (или в Cloudflare, если DNS там) создайте записи A на IP
сервера (и AAAA, если у сервера есть IPv6):

| Имя | Тип | Значение |
| --- | --- | --- |
| `phay.am` | A | IP сервера |
| `www.phay.am` | A | IP сервера |
| `app.phay.am` | A | IP сервера |
| `api.phay.am` | A | IP сервера |
| `servio.am` | A | IP сервера |
| `www.servio.am` | A | IP сервера |
| `admin.servio.am` | A | IP сервера |

Если DNS в Cloudflare, на время выпуска сертификатов включите режим
«DNS only» (серое облако), иначе Let's Encrypt не достучится до сервера.

Проверка с Mac: `dig +short app.phay.am` должен вернуть IP сервера.

### 4.3. Пользователь, обновления, swap

Под root на новом сервере:

```bash
apt update && apt -y upgrade
timedatectl set-timezone Asia/Yerevan

# Отдельный пользователь для деплоя (дальше всё под ним)
adduser deploy
usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh

# Swap 2 ГБ (обязательно при 2-4 ГБ RAM)
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

Дальше заходите как `ssh deploy@IP`. По желанию в
`/etc/ssh/sshd_config` поставьте `PasswordAuthentication no` и
`PermitRootLogin no`, затем `sudo systemctl restart ssh` (сначала
убедитесь, что вход по ключу под `deploy` работает).

### 4.4. Firewall

```bash
sudo apt -y install ufw
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

Важно: Docker при публикации порта (`ports:` в compose) обходит ufw. Поэтому
в `compose.prod.yaml` обоих проектов порты опубликованы только на
`127.0.0.1`, а база вообще не публикуется. Проверка после запуска:
`sudo ss -tlnp | grep -E ':(3000|3001|8080|5432)'` должен показывать только
`127.0.0.1`.

### 4.5. Docker, git, make, nginx, certbot

```bash
# Docker Engine + Compose из официального репозитория Docker
sudo apt -y install ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker deploy      # выйти и зайти снова, чтобы группа применилась

# Остальное
sudo apt -y install git make openssl nginx certbot

docker --version && docker compose version && nginx -v
```

Логи Docker по умолчанию растут без ограничений. Ограничьте их один раз:

```bash
echo '{ "log-driver": "json-file", "log-opts": { "max-size": "20m", "max-file": "5" } }' | sudo tee /etc/docker/daemon.json
sudo systemctl restart docker
```

### 4.6. Код: /var/www/projects

Вы писали `~/var/www/projects`. `~` значит домашнюю папку
(`/home/deploy/var/www/projects`); обычно используют `/var/www/projects`, так
и сделаем. Любой путь работает, команды ниже не зависят от него.

```bash
sudo mkdir -p /var/www/projects
sudo chown deploy:deploy /var/www/projects
```

Репозитории приватные, серверу нужен доступ на чтение. Надёжнее всего
**deploy key** на каждый репозиторий (только чтение, только этот репозиторий).
GitHub не разрешает один и тот же ключ в двух репозиториях, поэтому ключей
два:

```bash
ssh-keygen -t ed25519 -N "" -f ~/.ssh/phay_deploy   -C "server phay"
ssh-keygen -t ed25519 -N "" -f ~/.ssh/servio_deploy -C "server servio"
cat ~/.ssh/phay_deploy.pub     # → github.com/gurgenandreasyan77/phay → Settings → Deploy keys → Add (без write)
cat ~/.ssh/servio_deploy.pub   # → github.com/gurgenandreasyan77/servio → Settings → Deploy keys → Add

cat >> ~/.ssh/config <<'EOF'
Host github-phay
  HostName github.com
  User git
  IdentityFile ~/.ssh/phay_deploy
  IdentitiesOnly yes
Host github-servio
  HostName github.com
  User git
  IdentityFile ~/.ssh/servio_deploy
  IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config

cd /var/www/projects
git clone git@github-phay:gurgenandreasyan77/phay.git phay
git clone git@github-servio:gurgenandreasyan77/servio.git servio
```

### 4.7. Nginx и сертификаты

Порядок важен: настоящие конфиги ссылаются на сертификаты, которых ещё нет,
и nginx их не примет. Поэтому сначала временный конфиг только для проверки
Let's Encrypt, потом сертификаты, потом настоящие конфиги.

```bash
cd /var/www/projects/phay

# 1. Общие TLS-настройки (один файл на сервер)
sudo cp deploy/tls-params.conf /etc/nginx/snippets/tls-params.conf

# 2. Временный конфиг для выпуска сертификатов
sudo mkdir -p /var/www/certbot
sudo cp deploy/acme.nginx.conf /etc/nginx/sites-available/acme
sudo ln -sf /etc/nginx/sites-available/acme /etc/nginx/sites-enabled/acme
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# 3. Сертификаты (email для писем об истечении)
sudo certbot certonly --webroot -w /var/www/certbot --agree-tos -m you@example.com \
  -d phay.am -d www.phay.am -d app.phay.am -d api.phay.am
sudo certbot certonly --webroot -w /var/www/certbot --agree-tos -m you@example.com \
  -d servio.am -d www.servio.am -d admin.servio.am

# 4. Настоящие конфиги обоих проектов
sudo rm /etc/nginx/sites-enabled/acme
sudo cp deploy/phay.nginx.conf /etc/nginx/sites-available/phay.am
sudo ln -sf /etc/nginx/sites-available/phay.am /etc/nginx/sites-enabled/phay.am
sudo cp ../servio/deploy/servio.nginx.conf /etc/nginx/sites-available/servio.am
sudo ln -sf /etc/nginx/sites-available/servio.am /etc/nginx/sites-enabled/servio.am
sudo nginx -t && sudo systemctl reload nginx

# 5. Перезагрузка nginx после каждого автопродления сертификата
echo -e '#!/bin/sh\nsystemctl reload nginx' | sudo tee /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
sudo certbot renew --dry-run
```

Продление идёт само (таймер `certbot.timer`), через тот же путь
`/.well-known/acme-challenge/` на порту 80, который есть в настоящих
конфигах. На nginx 1.25+ будет предупреждение `listen ... http2 is
deprecated`: оно безвредно, на Ubuntu 24.04 (nginx 1.24) его нет.

Если порты `API_PORT`, `ADMIN_PORT` или `FRONTEND_PORT` в `.env.prod`
меняются, поменяйте их и в `upstream` в конфигах nginx.

## 5. Первый запуск Phay в продакшене

```bash
cd /var/www/projects/phay

# 1. .env.prod с новым APP_KEY и случайным паролем базы
make prod-init
nano .env.prod          # заполнить почту, ANTHROPIC_API_KEY, GOOGLE_*, см. раздел 7

# 2. Проверить, что файл и compose сходятся
make prod-config

# 3. Собрать и запустить (сборка 5-10 минут)
make prod-build
make prod-up
make prod-ps            # postgres, backend, api, admin: healthy; scheduler: running

# 4. Таблицы. Миграции сами создают бизнес servio и сайт servio.am с ключом
make prod-migrate

# 5. Проверка изнутри сервера и снаружи
curl -s http://127.0.0.1:8080/api/health
curl -sI https://api.phay.am/api/health | head -1
curl -sI https://app.phay.am | head -1      # 307 → /admin

# 6. Первый супер-админ: команда печатает одноразовую ссылку (60 минут)
make prod-bootstrap email=you@phay.am name="Gurgen Andreasyan"
```

Откройте ссылку, задайте пароль, подключите 2FA в приложении-аутентификаторе
(Google Authenticator, 1Password, Authy) и сохраните коды восстановления.
Для персонала платформы 2FA обязательна. Второй раз `platform:bootstrap` не
сработает: остальных пользователей приглашают из админки, письмом.

**Сразу сохраните `.env.prod` в надёжное место** (менеджер паролей). Без
`APP_KEY` из него не расшифровать 2FA, токены Google и секреты webhook даже
из бэкапа базы.

## 6. Подключение сайта Servio

Сайт `servio.am` уже есть в базе после миграций (бизнес `servio`), его
домены `servio.am` и `www.servio.am` автоматически разрешены для CORS.

```bash
# 1. Ключ сайта
cd /var/www/projects/phay
make prod-site-keys          # строка servio.am, колонка с site_pk_...

# 2. Сайт: см. docs/DEPLOY.md в репозитории servio
cd /var/www/projects/servio
make prod-init               # .env.prod со случайным REVALIDATE_SECRET
nano .env.prod               # NEXT_PUBLIC_SITE_KEY=site_pk_... из шага 1
make prod-build prod-up
grep REVALIDATE_SECRET .env.prod
```

3. В админке: **app.phay.am → Կայքեր (Platform → Sites) → servio.am**:
   - «Քեշի թարմացման հասցե (webhook)»: `https://servio.am/api/revalidate`
   - пароль webhook: значение `REVALIDATE_SECRET` из `servio/.env.prod`
   - сохранить и нажать «Ստուգել webhook-ը»: должно ответить успехом.

После этого любое изменение услуг, цен, блога, контактов в админке сразу
обновляет сайт.

На новом пустом продакшене у Servio нет услуг и цен. Либо перенесите данные
(раздел 11), либо залейте демо-каталог:
`make prod-artisan cmd="db:seed --class=ServioDemoSeeder --force"`.

Новый сайт другого бизнеса подключается так же: Платформа → Բիզնեսներ
(создать бизнес) → Կայքեր (добавить сайт с доменом, скопировать ключ), или из
консоли:

```bash
make prod-artisan cmd="platform:tenant:create 'Test Shop' --owner=owner@example.com"
make prod-artisan cmd="platform:site:create test-shop shop.am --domain=shop.am --origin=https://shop.am"
```

## 7. Переменные .env.prod

Обязательные помечены ★. Без них `compose.prod.yaml` не запустится и скажет,
чего не хватает.

### Docker и база

| Ключ | Значение | Откуда взять |
| --- | --- | --- |
| `COMPOSE_PROJECT_NAME` | `phay-prod` | Не менять после первого запуска: смена имени = новые пустые тома |
| `API_PORT` | `8080` | Порт API на 127.0.0.1, должен совпадать с `upstream phay_api` в nginx |
| `ADMIN_PORT` | `3001` | Порт админки на 127.0.0.1, `upstream phay_admin` |
| `POSTGRES_DB`, `POSTGRES_USER` | `phay` | Можно оставить |
| ★ `POSTGRES_PASSWORD` | случайная строка | `make prod-init` ставит сам (`openssl rand -hex 24`). Задаётся до первого запуска: Postgres запоминает пароль при создании тома, поздняя смена в файле его не меняет |

### Приложение

| Ключ | Значение | Откуда взять |
| --- | --- | --- |
| `APP_NAME` | `Phay` | Имя в письмах |
| ★ `APP_KEY` | `base64:...` | `make prod-init` генерирует сам, или `make prod-key`. Шифрует 2FA, токены Google, секреты webhook. Никогда не менять на живой базе. При переносе данных с локалки взять ключ из `api/.env` (раздел 11) |
| ★ `APP_URL` | `https://api.phay.am` | Адрес API, из него строятся ссылки на файлы `/storage/...` |
| ★ `ADMIN_URL` | `https://app.phay.am` | Ссылки в письмах приглашения и сброса пароля |
| `CORS_ALLOWED_ORIGINS` | `https://app.phay.am` | Дополнительные origin для браузера; домены подключённых сайтов добавляются сами |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | `en` | Язык системных сообщений Laravel; интерфейс админки на армянском независимо от этого |
| `LOG_CHANNEL` | `daily` | Лог по дням в томе `app_logs` (`make prod-logs-app`) |
| `LOG_DAILY_DAYS` | `14` | Сколько дней хранить |
| `LOG_LEVEL` | `info` | `debug` только для поиска проблемы |
| `LEADS_RATE_LIMIT_PER_MINUTE` | `5` | Заявок в минуту с одного IP |
| `ADMIN_LOGIN_RATE_LIMIT_PER_MINUTE` | `5` | Попыток входа в минуту с одного IP |

### Почта (приглашения, сброс пароля, уведомления о входе)

Без почты нельзя пригласить сотрудника или владельца бизнеса. Подойдёт любой
SMTP. Пример с **Resend** (бесплатно до 3000 писем в месяц):

1. resend.com → Domains → Add domain → `phay.am`.
2. Добавить у регистратора DNS-записи, которые покажет Resend (SPF `TXT`,
   DKIM `TXT`/`CNAME`, по желанию DMARC `TXT _dmarc`), дождаться «Verified».
3. API Keys → Create (права Sending access).

| Ключ | Resend | Brevo |
| --- | --- | --- |
| `MAIL_MAILER` | `smtp` | `smtp` |
| `MAIL_SCHEME` | `smtp` (STARTTLS на 587) или `smtps` (465) | `smtp` |
| `MAIL_HOST` | `smtp.resend.com` | `smtp-relay.brevo.com` |
| `MAIL_PORT` | `587` | `587` |
| `MAIL_USERNAME` | `resend` | логин SMTP из Brevo → SMTP & API |
| `MAIL_PASSWORD` | API key `re_...` | SMTP key |
| `MAIL_FROM_ADDRESS` | `no-reply@phay.am` (домен должен быть подтверждён) | |
| `MAIL_FROM_NAME` | `Phay` | |

Проверка после настройки: пригласить себя на второй email из админки
(Պլատֆորմի թիմ) и посмотреть, дошло ли письмо и не в спаме ли оно.

### SMS (подтверждение телефона)

| Ключ | Значение | Откуда взять |
| --- | --- | --- |
| `SMS_DRIVER` | `log` или `twilio` | `log`: коды только в логе, SMS не уходят. Для начала достаточно |
| `TWILIO_ACCOUNT_SID` | `AC...` | console.twilio.com → Account Info |
| `TWILIO_AUTH_TOKEN` | | там же, Auth Token |
| `TWILIO_FROM` | `+1...` или Messaging Service | Phone Numbers → Buy a number (с SMS), или Messaging → Services. Для Армении проверьте Geo Permissions (Messaging → Settings) |

### Безопасность входа

| Ключ | Значение | Смысл |
| --- | --- | --- |
| `TWO_FACTOR_REQUIRED_FOR_OWNERS` | `true` | 2FA обязательна и для владельцев бизнесов (для персонала платформы всегда) |
| `TWO_FACTOR_ISSUER` | `Phay` | Название в приложении-аутентификаторе |

Необязательные (можно добавить в `.env.prod`): `INVITATION_TTL_MINUTES`
(4320, ссылка приглашения 72 ч), `BOOTSTRAP_TTL_MINUTES` (60),
`PASSWORD_RESET_TTL_MINUTES` (60), `SANCTUM_EXPIRATION` (10080 минут,
сессия админки 7 дней).

### Платформа

| Ключ | Значение | Смысл |
| --- | --- | --- |
| `PLATFORM_DEFAULT_TENANT` | `servio` | Бизнес по умолчанию для консольных команд и сидеров |
| `PLATFORM_SUBSCRIPTION_WARN_DAYS` | `10` | За сколько дней до конца подписки предупреждать бизнес |

### ИИ-ассистент

| Ключ | Значение | Откуда взять |
| --- | --- | --- |
| `ANTHROPIC_API_KEY` | `sk-ant-...` | platform.claude.com → Settings → API keys → Create key. На аккаунте нужны средства: Settings → Billing. Без ключа страница ассистента пишет, что он не подключён |
| `ASSISTANT_MODEL` | `claude-opus-5-5` | `claude-sonnet-5-5` примерно вдвое дешевле |
| `ASSISTANT_EFFORT` | `low` | `low`, `medium`, `high`: глубина рассуждений, цена и время ответа растут |
| `ASSISTANT_MONTHLY_LIMIT` | `30` | Запросов в месяц для бизнеса без тарифа; у тарифов свой лимит (Փաթեթներ և գներ) |

Если ассистент отвечает ошибкой, персонал платформы видит её код:
`invalid_key` (ключ неверный), `no_credit` (нет средств), `bad_model`,
`rate_limited`, `network`.

### Google Calendar

1. console.cloud.google.com → создать проект «Phay».
2. APIs & Services → Library → **Google Calendar API** → Enable.
3. OAuth consent screen (Google Auth Platform): тип External, имя «Phay»,
   email поддержки, домен `phay.am`, ссылка на политику конфиденциальности;
   scope `.../auth/calendar.readonly`. Пока приложение в режиме Testing,
   подключаться могут только добавленные Test users (до 100); для всех нужна
   проверка Google (Publish app).
4. Credentials → Create credentials → OAuth client ID → **Web application**,
   Authorized redirect URIs: `https://app.phay.am/api/admin/google/callback`
   (для локалки отдельно `http://localhost:3001/api/admin/google/callback`).
5. Client ID и Client secret в `.env.prod`:

| Ключ | Значение |
| --- | --- |
| `GOOGLE_CLIENT_ID` | `....apps.googleusercontent.com` |
| `GOOGLE_CLIENT_SECRET` | `GOCSPX-...` |
| `GOOGLE_REDIRECT_URI` | `https://app.phay.am/api/admin/google/callback`, ровно как в Google Cloud |

После любого изменения `.env.prod`: `make prod-up` (compose пересоздаст
контейнеры с новыми значениями). `make prod-restart` новые значения не
подхватывает.

## 8. Бэкапы и восстановление

`make prod-backup` кладёт в `backups/` (папка в `.gitignore`, права 700):

- `phay-db-ГГГГММДД-ЧЧММСС.sql.gz`: дамп базы без владельца и прав, его можно
  восстановить под любым пользователем Postgres;
- `phay-storage-ГГГГММДД-ЧЧММСС.tar.gz`: загруженные файлы (логотипы, фото,
  видео) из тома `public_storage`.

Ежедневный бэкап в 03:30 и удаление старше 14 дней (`crontab -e` под
`deploy`):

```cron
30 3 * * * cd /var/www/projects/phay && make prod-backup prod-backup-prune >> backups/cron.log 2>&1
```

Бэкап на том же сервере не спасёт, если пропадёт сервер. Забирайте его к себе
хотя бы раз в неделю, с Mac:

```bash
rsync -av deploy@IP:/var/www/projects/phay/backups/ ~/Backups/phay/
```

И держите копию `.env.prod` (там `APP_KEY`) отдельно от бэкапов базы.

Восстановление:

```bash
# База: останавливает приложение, ЗАМЕНЯЕТ базу дампом, запускает снова
make prod-backup                         # на всякий случай текущая копия
make prod-restore file=backups/phay-db-20261101-033000.sql.gz confirm=yes
make prod-migrate                        # если дамп старше кода

# Файлы: распаковывает поверх текущих
make prod-restore-storage file=backups/phay-storage-20261101-033000.tar.gz
```

## 9. Обновление

```bash
cd /var/www/projects/phay
make prod-deploy
```

`prod-deploy` делает по порядку: `prod-backup`, `git pull --ff-only`,
`prod-build`, `prod-up`, `prod-migrate`, `prod-ps`. Пока собираются образы,
работает старая версия; при `prod-up` контейнеры пересоздаются, перерыв
несколько секунд. Если `git pull` отказался (на сервере правили файлы
репозитория), посмотрите `git status` и уберите локальные правки: на сервере
меняется только `.env.prod`.

Сначала обновляйте phay, потом servio: сайт может ждать новый API.

## 10. Откат

Если после обновления что-то сломалось:

```bash
cd /var/www/projects/phay
git log --oneline -10                     # найти последний рабочий коммит
git checkout <коммит>
make prod-build prod-up
```

Если обновление включало миграции, которые старый код не понимает,
восстановите базу из бэкапа, сделанного `prod-deploy` перед обновлением (самый
свежий `phay-db-*` в `backups/`):

```bash
make prod-restore file=backups/phay-db-<время перед обновлением>.sql.gz confirm=yes
```

Данные, внесённые между обновлением и откатом, при этом пропадут. Вариант без
потери: `make prod-artisan cmd="migrate:rollback --step=1 --force"` на НОВОМ
коде (до `git checkout`), если миграция обратима.

Когда исправление готово, вернитесь на master: `git checkout master` и
`make prod-deploy`.

## 11. Перенос данных

### С локальной машины на новый продакшен

Если реальные данные Servio сейчас в локальной базе на Mac:

```bash
# На Mac, в папке phay
make local-backup                                   # backups/phay-local-*.sql
tar -C api/storage/app/public -czf backups/storage-local.tar.gz .
grep '^APP_KEY=' api/.env                          # этот ключ нужен в .env.prod
scp backups/phay-local-*.sql backups/storage-local.tar.gz deploy@IP:/var/www/projects/phay/backups/
```

На сервере, **до** `prod-bootstrap` (в дампе уже есть ваш супер-админ):

```bash
cd /var/www/projects/phay
nano .env.prod             # APP_KEY= ключ из локального api/.env
make prod-up               # пересоздать контейнеры с этим ключом
make prod-restore file=backups/phay-local-XXXX.sql confirm=yes
make prod-migrate
make prod-restore-storage file=backups/storage-local.tar.gz
# ссылки на файлы в базе указывают на localhost: переписать на api.phay.am
make prod-artisan cmd="platform:media:rehost http://localhost:8080 https://api.phay.am --dry-run"
make prod-artisan cmd="platform:media:rehost http://localhost:8080 https://api.phay.am"
```

Без того же `APP_KEY` перенос тоже пройдёт, но 2FA, Google и webhook придётся
настроить заново (`make prod-artisan cmd="platform:two-factor:reset you@phay.am"`).

После переноса в Platform → Sites поменяйте webhook servio.am с локального
`http://host.docker.internal:3000/...` на `https://servio.am/api/revalidate`
и пароль на `REVALIDATE_SECRET` из `servio/.env.prod`.

### Со старого сервера (старый монорепо servio)

Если servio.am уже работает на сервере со старым репозиторием (бэкенд и
база в одном проекте `servio`):

1. Сделайте дамп старой базы и архив файлов:
   `docker compose exec -T postgres pg_dump --no-owner --no-acl -U <user> <db> > old.sql`
   и `docker compose cp backend:/var/www/html/storage/app/public ./old-storage`.
2. Возьмите `APP_KEY` из старого `.env`.
3. Остановите старый стек (`docker compose down` в старой папке), уберите
   старые конфиги nginx для servio.am.
4. Дальше как выше: `APP_KEY` в `.env.prod`, `prod-restore`, `prod-migrate`,
   `prod-restore-storage`, `platform:media:rehost https://servio.am https://api.phay.am`.
5. Старую папку и тома удаляйте только после того, как всё проверено.

## 12. Локальная разработка

Нужен Docker Desktop (на Mac Apple Silicon работает нативно).

### С нуля

```bash
git clone git@github.com:gurgenandreasyan77/phay.git && cd phay
make local-setup            # .env, api/.env, composer install, образы, APP_KEY, миграции
make local-bootstrap email=you@example.com name="Your Name"
```

Ссылку из вывода открыть в браузере. Письма и SMS-коды локально не
отправляются, а пишутся в `api/storage/logs/laravel.log`.

- Админка: http://localhost:3001
- API: http://localhost:8080/api/health
- Демо-каталог Servio: `make local-seed-servio`

### Если локально уже всё работает (после переезда)

Ничего пересоздавать не нужно: тома и `api/.env` остаются. Из корневого
`.env` используются только `COMPOSE_PROJECT_NAME`, порты и `POSTGRES_*`;
остальные строки можно удалить (Laravel берёт их из `api/.env`). Проверьте,
что в `api/.env` есть ваши `ANTHROPIC_API_KEY` и `GOOGLE_*`, если раньше они
были только в корневом `.env`.

### Где что настраивать локально

- Порты, имя проекта, пароль локальной базы: корневой `.env`.
- Всё остальное (ключи, почта, ИИ, Google): `api/.env`. После изменения
  `make local-restart s=backend`.
- `APP_URL`, `ADMIN_URL`, `CORS_ALLOWED_ORIGINS`, `DB_*` в Docker задаёт
  `compose.yaml` из портов и базы корневого `.env`, их не нужно трогать в
  `api/.env`.

### Каждый день

```bash
make local-up / make local-down / make local-logs s=backend
make local-migrate                  # после git pull с новыми миграциями
make local-restart s=backend        # после изменений PHP-кода
rm -rf admin/.next && make local-restart s=admin   # после изменений зависимостей админки
make local-test                     # тесты API + lint и tsc админки
```

### Сайт Servio локально

Phay должен быть запущен. Потом в папке servio: `make local-setup`, в
`.env` `NEXT_PUBLIC_SITE_KEY` из `make local-site-keys`, `make local-restart`.
Webhook сайта в локальной админке: `http://host.docker.internal:3000/api/revalidate`,
пароль `dev_revalidate_secret`. Подробно в `docs/DEPLOY.md` репозитория servio.

## 13. Если что-то не работает

| Симптом | Что проверить |
| --- | --- |
| `502 Bad Gateway` на app/api.phay.am | `make prod-ps`: контейнер не healthy? `make prod-logs s=backend`. Совпадают ли порты в `.env.prod` и `upstream` в nginx |
| `make prod-*` пишет `APP_KEY must be set` | Не заполнен обязательный ключ в `.env.prod` |
| Сертификат не выпускается | DNS указывает на сервер (`dig +short`), порт 80 открыт (`sudo ufw status`), в Cloudflare «DNS only» |
| Вход в админку: ошибка 500 | `make prod-logs-app`. Часто: не прошли миграции (`make prod-migrate-status`) |
| Письма не приходят | `make prod-logs-app` (ошибка SMTP), домен подтверждён у провайдера, SPF/DKIM |
| Сайт servio.am пустой или 401 в логах | Неверный `NEXT_PUBLIC_SITE_KEY` в `servio/.env.prod` или сайт выключен в Կայքեր |
| Изменения в админке не видны на сайте | Webhook servio.am и пароль в Կայքեր, кнопка «Ստուգել webhook-ը» |
| Предупреждения о подписке не уходят | `make prod-logs s=scheduler` |
| Диск заполнился | `docker system df`, `docker image prune -a` (удаляет неиспользуемые образы), старые бэкапы `make prod-backup-prune` |
| Порт виден снаружи | `sudo ss -tlnp`: всё, кроме 22, 80, 443, должно быть на 127.0.0.1 |
