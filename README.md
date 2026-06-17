# Trello-Backup

[Trello-Backup](https://github.com/mattab/trello-backup) is a simple script that Backups all your [Trello.com](https://trello.com/) boards and cards, one JSON file per board, for total peace of mind. This is a simple php script which uses the Trello.com API to securely fetch all your boards and store them on your computer.

## Requirements

This is a simple php script which requires PHP installed on your system:
`sudo apt-get install php7`

## Usage

- Download the code in a 'trello-backup' directory with:
  `git clone https://github.com/mattab/trello-backup.git trello-backup`
- Duplicate the `config.example.php` file to `config.php` and fill in your details (as follows)
- With your browser go to: [https://trello.com/1/appKey/generate](https://trello.com/1/appKey/generate) - It will give you your public 'Key' for Trello API.
- Edit the file trello-backup/config.php and set `$key` to your 'Key'.
- Then Run the script:
  `php trello-backup/trello-backup.php`
  It will output a URL that you can visit with your browser to get the Application Token. Visit this URL. Then click 'Allow' and copy the token string.
- Edit `config.php` and paste this token in `$application_token`.
- You are ready! Run this script will download your Trello boards:
  `php trello-backup/trello-backup.php`
  It will create a file named `trello-org-[OrganizationNameHere]-board-[NameHere].json` for each of your board.
  Also recommended: setup a crontab to automatically backup every day or every week.

Enjoy!

## How to backup several accounts

If you want to backup multiple Trello accounts, you can make multiple copies of `example-config.php` with different file names. Run `trello-backup.php` once for each account, specifying the path to the config file as an argument. For example, `php trello-backup.php account1.php`.

## Why Trello-Backup?

Trello.com is a really wonderful free tool, but it has one technical issue 'by design': it is not [Free Software](http://www.fsf.org/) that we can self host ourselves.

Also the fine weather can turn to rain pretty quickly: We cannot trust the clouds 100%.

Plus I'm pretty sure others would like to backup their Trello data!

## Who is Trello-Backup for?

For anyone using Trello.com who wants to ! but especially:

- if you store a lot of great ideas and tasks,
- if you carefully plan long checklists full of unique requirements and thoughts,
- if you have not only one board but several boards all of them containing important data,
- if you are thinking of going on a No-Internet holiday for a few weeks and wish to access your boards while offline...

## What does this do in terms of clouds?

This little script keeps your data out of the clouds!

## What is Trello?

Trello is a free web-based project management application made by Fog Creek Software.
Trello uses a paradigm for managing projects known as kanban, a method that had originally been popularized by Toyota in the 1980s for supply chain management. Projects are represented by boards, which contain lists (corresponding to task lists). Lists contain cards (corresponding to tasks). Cards are supposed to progress from one list to the next (via drag-and-drop), for instance mirroring the flow of a feature from idea to implementation. Users can be assigned to cards. Users and boards can be grouped into organizations.

Source: Trello on [Wikipedia](http://en.wikipedia.org/wiki/Trello)

## Revoking your Trello API Token after use

After backuping your Trello boards, you can easily revoke your token if you wish. Go to [trello.com/my/account](https://trello.com/my/account), scroll down to "Applications" and click "Revoke". See [this Trello help page](https://help.trello.com/article/1183-revoking-a-trello-token) for more info.

## Common Issues

Fatal error: Maximum execution time of x seconds exceed in (Directory here). The simple fix here is to edit your php.ini file and set the max_execution_time attribute to 0 to allow your script to run as long as you need it to.

# Trello Manager

`trello-manager.php` is an additional CLI tool that lets you manage your Trello boards interactively — add cards, manage checklists, attach files, rename cards, and more.

### Setup

No additional setup needed — it uses the same `config.php` as the backup script.

### Usage

```
php trello-manager.php <command> [options]
```

### Commands

| Command          | Description                      | Example                                                                                                                                                                                                    |
| ---------------- | -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `list-boards`    | List all boards                  | `php trello-manager.php list-boards`                                                                                                                                                                       |
| `list-lists`     | List lists on a board            | `php trello-manager.php list-lists --board "My Board"`                                                                                                                                                     |
| `list-cards`     | List cards in a list             | `php trello-manager.php list-cards --board "My Board" --list "To Do"`                                                                                                                                      |
| `add-card`       | Add a card to a list             | `php trello-manager.php add-card --board "My Board" --list "To Do" --title "My Card" --desc "Optional description"`                                                                                        |
| `add-checklist`  | Add a checklist to a card        | `php trello-manager.php add-checklist --board "My Board" --list "To Do" --card "My Card" --name "Steps"`                                                                                                   |
| `add-item`       | Add an item to a checklist       | `php trello-manager.php add-item --board "My Board" --list "To Do" --card "My Card" --checklist "Steps" --item "First step"`                                                                               |
| `check-item`     | Mark a checklist item done       | `php trello-manager.php check-item --board "My Board" --list "To Do" --card "My Card" --checklist "Steps" --item "First step"`                                                                             |
| `uncheck-item`   | Mark a checklist item incomplete | `php trello-manager.php uncheck-item --board "My Board" --list "To Do" --card "My Card" --checklist "Steps" --item "First step"`                                                                           |
| `add-attachment` | Add an attachment to a card      | `php trello-manager.php add-attachment --board "My Board" --list "To Do" --card "My Card" --url "https://example.com/file.pdf" --name "file.pdf"`                                                          |
| `set-desc`       | Set card description             | `php trello-manager.php set-desc --board "My Board" --list "To Do" --card "My Card" --description "New description"`                                                                                       |
| `rename-card`    | Change a card's title            | `php trello-manager.php rename-card --board "My Board" --list "To Do" --card "Old Title" --new-title "New Title"`                                                                                          |
| `move-card`      | Move a card to another list      | `php trello-manager.php move-card --board "My Board" --list "To Do" --card "My Card" --to-list "Done"`                                                                                                     |
| `archive-card`   | Archive a card                   | `php trello-manager.php archive-card --board "My Board" --list "To Do" --card "My Card"`                                                                                                                   |
| `query`          | View full board details          | `php trello-manager.php query --board "My Board"`<br>`php trello-manager.php query --board "My Board" --list "To Do"`<br>`php trello-manager.php query --board "My Board" --list "To Do" --card "My Card"` |

### Fuzzy Matching

Board names, list names, card titles, checklist names, and item text all support **case-insensitive partial matching** — you don't need to type the exact name. For example:

```
php trello-manager.php add-card --board "My" --list "Do" --title "New task"
```

## Credits

This is my first Github project!
~ [Matthieu Aubry](http://matthieu.net/)

Kuddos to [Zander](https://github.com/zph/) on Github for his help, when I was trying to use his [trello-archiver](https://github.com/zph/trello-archiver).

This script officially started from [this Gist](https://gist.github.com/4498847)!

The README Is longer than the script - I'm also practising Markdown.

<!-- Piwik Image Tracker -->
<img src="http://demo.piwik.org/piwik.php?idsite=41&amp;rec=1&amp;action_name=Readme" style="border:0" alt="" />
<!-- End Piwik -->
