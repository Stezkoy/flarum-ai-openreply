# Расширение AI Open-Reply для Flarum

![Лицензия](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/stezkoy/flarum-ai-openreply.svg)](https://packagist.org/packages/stezkoy/flarum-ai-openreply) [![Total Downloads](https://img.shields.io/packagist/dt/stezkoy/flarum-ai-openreply.svg)](https://packagist.org/packages/stezkoy/flarum-ai-openreply)

Расширение для [Flarum](http://flarum.org).

Автоматически отвечает на новые обсуждения (или на каждое сообщение автора темы) с помощью AI-ассистента. Ответы генерируются [opencode](https://opencode.ai) — headless-инстансом [opencode serve](https://opencode.ai/docs/server/) — и публикуются от имени назначенного пользователя-ассистента.

Для каждого обсуждения создаётся своя постоянная сессия opencode, поэтому ассистент сохраняет полный контекст разговора. Для этих сессий запрещено использование инструментов, поэтому ассистент отвечает только текстом.

Форк `michaelbelgium/flarum-ai-autoreply`, переработанный для работы исключительно с серверным API opencode 1.x.

## Требования

- **Flarum >= 2.0** и **PHP 8.4**
- Запущенный инстанс [opencode-сервера](https://opencode.ai/docs/server/), доступный с хоста, на котором работает Flarum

## Установка

Установите через composer:

```sh
composer require stezkoy/flarum-ai-openreply
```

Затем выполните миграции:

```sh
php flarum migrate
```

## Установка opencode на Ubuntu

Установите CLI `opencode` на машину, где работает Flarum:

```sh
curl -fsSL https://opencode.ai/install | bash
```

Скрипт устанавливает opencode в `~/.opencode/bin` и выводит дальнейшие шаги. Либо установите его глобально через npm, чтобы бинарник оказался в `PATH` (удобно для systemd-сервиса позже):

```sh
sudo apt-get install -y nodejs npm
sudo npm install -g opencode-ai
which opencode   # -> /usr/bin/opencode
```

Настройте хотя бы одного AI-провайдера для opencode (это делается один раз на машину, а не на сессию):

```sh
opencode auth login
```

## Настройка opencode-сервера

> AI-провайдер(-ы) и API-ключи настраиваются внутри самого opencode. Это расширение общается с opencode-сервером только по HTTP.

### Безопасный вариант по умолчанию: только localhost (рекомендуется)

По умолчанию сервер слушает `127.0.0.1`, поэтому он доступен только с той же машины. Запустите его с basic-аутентификацией и фиксированным портом:

```sh
OPENCODE_SERVER_PASSWORD=your-strong-password opencode serve --hostname 127.0.0.1 --port 4096
```

URL расширения по умолчанию `http://localhost:4096` соответствует этой команде. Ничего не выставляется в сеть; трогать брандмауэр не нужно.

### Внешний доступ (не рекомендуется)

Делайте так только если opencode-сервер должен жить на другой машине, чем Flarum. Обязательно задавайте пароль:

```sh
OPENCODE_SERVER_PASSWORD=your-strong-password opencode serve \
  --hostname 0.0.0.0 --port 4096 \
  --cors https://forum.example.com
```

Примечания:

- С `--hostname 0.0.0.0` сервер слушает все интерфейсы — ограничьте доступ брандмауэром (`ufw allow from <ip-flarum> to any port 4096`) и по возможности спрячьте его за TLS (обратный прокси Nginx/Caddy).
- `--cors` нужен только для клиентов в браузере. Это расширение вызывает API из PHP, поэтому CORS для его работы не требуется.

### Параметры `opencode serve`

| Флаг                 | Описание                                        | По умолчанию  |
|----------------------|-------------------------------------------------|---------------|
| `--port`             | Порт для прослушивания                          | `4096`        |
| `--hostname`         | Хост для прослушивания                          | `127.0.0.1`   |
| `--mdns`             | Включить mDNS-обнаружение (подразумевает `0.0.0.0`) | `false`   |
| `--mdns-domain`      | Доменное имя mDNS                               | `opencode.local` |
| `--cors`             | Дополнительные источники для браузера (можно повторять) | `[]`    |

Аутентификация управляется переменными окружения:

- `OPENCODE_SERVER_PASSWORD` — включает HTTP basic-аутентификацию (обязательно, если сервер доступен кому-то ещё).
- `OPENCODE_SERVER_USERNAME` — имя пользователя для basic-аутентификации, по умолчанию `opencode` (именно это имя отправляет расширение).

### Запуск как systemd-сервис

Создайте `/etc/systemd/system/opencode.service`:

```ini
[Unit]
Description=opencode headless server (AI Open-Reply)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=opencode
WorkingDirectory=/var/lib/opencode
Environment=OPENCODE_SERVER_PASSWORD=your-strong-password
ExecStart=/usr/bin/opencode serve --hostname 127.0.0.1 --port 4096
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

Поправьте `ExecStart`, если `opencode` находится в другом месте (`which opencode`), затем:

```sh
sudo useradd -r -s /usr/sbin/nologin --home-dir /var/lib/opencode opencode
sudo mkdir -p /var/lib/opencode && sudo chown opencode:opencode /var/lib/opencode
sudo systemctl daemon-reload
sudo systemctl enable --now opencode
sudo systemctl status opencode
```

> Учётная запись `User=opencode` хранит собственные учётные данные провайдера и данные сессий. Добавьте `Environment=OPENCODE_SERVER_USERNAME=...`, если вы изменили имя пользователя basic-аутентификации.

## Настройка

На странице настроек расширения в админке:

- **URL opencode-сервера** — адрес вашего headless-сервера opencode.
- **Пароль opencode-сервера** — значение `OPENCODE_SERVER_PASSWORD`, если включена basic-аутентификация (имя пользователя — `opencode`).
- **Агент** — опциональный [агент opencode](https://opencode.ai/docs/agents/) для использования; оставьте пустым для агента по умолчанию.
- **Доступные модели** — известные бесплатные модели (по одной в строке), которые заполняют выпадающий список **Модель**. По умолчанию это `opencode/big-pickle` и `opencode/mouse-spark`.
- **Модель** — бесплатная модель для ответов; формат `provider/model`. Оставьте «по умолчанию», чтобы использовать модель агента.
- **Пользователь-ассистент** — ID пользователя, от имени которого публикуются ответы AI (обязательно).
- **Бейдж пользователя-ассистента** — текст, отображаемый под постами ассистента.
- **Отвечать при создании обсуждения** — если включено, AI отвечает только при создании обсуждения. Если выключено, обсуждение превращается в чат между автором и ассистентом.
- **Теги** — ограничить работу ассистента конкретными тегами.

Агент и **Модель** применяются при первом создании сессии обсуждения; изменение их позже затрагивает только новые обсуждения. Инструкции ассистента («системный промпт») настраиваются на самом агенте, например в `opencode.json`:

```json
{
  "agent": {
    "prompt": "You are a helpful assistant on a Flarum forum. Answer in the language of the user's post."
  }
}
```

Также выдайте нужным группам пользователей разрешение «Использовать AI-ассистента».

## Возможности

- Автоответ на новые обсуждения с помощью AI
- Режим чата: обсуждение становится личным чатом между автором и ассистентом
- Постоянный контекст для каждого обсуждения (одна сессия opencode на обсуждение)
- Только текстовые ответы (все инструменты opencode запрещены через набор правил разрешений сессии)
- Ограничение работы ассистента выбранными тегами
- Контроль прав на то, кто может запускать автоответ

## Обновление

```sh
composer update stezkoy/flarum-ai-openreply
php flarum migrate
php flarum cache:clear
```

## Ссылки

- [Packagist](https://packagist.org/packages/stezkoy/flarum-ai-openreply)
- [GitHub](https://github.com/stezkoy/flarum-ai-openreply)
- [Discuss](https://discuss.flarum.org/d/38244)
