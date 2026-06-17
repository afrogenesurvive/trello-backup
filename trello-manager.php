<?php
/**
 * Trello Manager - A CLI tool to manage Trello boards, cards, checklists, and more.
 *
 * Usage: php trello-manager.php <command> [options]
 *
 * Commands:
 *   list-boards                           List all boards
 *   list-lists    --board <name>          List lists on a board
 *   list-cards    --board <name> --list <name>   List cards in a list
 *   add-card      --board <n> --list <n> --title <t> [--desc <d>] [--pos top|bottom]
 *   add-checklist --board <n> --list <n> --card <t> --name <n> [--pos top|bottom]
 *   add-item      --board <n> --list <n> --card <t> --checklist <n> --item <t> [--pos top|bottom]
 *   move-item     --board <n> --list <n> --card <t> --checklist <n> --item <t> --pos top|bottom
 *   check-item    --board <n> --list <n> --card <t> --checklist <n> --item <t>
 *   uncheck-item  --board <n> --list <n> --card <t> --checklist <n> --item <t>
 *   add-attachment --board <n> --list <n> --card <t> --url <u> [--name <f>]
 *   set-desc      --board <n> --list <n> --card <t> --description <d>
 *   rename-card   --board <n> --list <n> --card <t> --new-title <t>
 *   move-card     --board <n> --list <n> --card <t> --to-list <n>
 *   archive-card  --board <n> --list <n> --card <t>
 *   query         --board <n> [--list <n>] [--card <t>]
 *
 * License: GPL v3 or later
 */

// ─── Configuration ───────────────────────────────────────────────────────────

if ($argc < 2) {
    showUsage();
    exit(1);
}

$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
if (!file_exists($config_file)) {
    die("Please duplicate config.example.php to config.php and fill in your details.\n");
}
require_once $config_file;

if (empty($application_token) || strlen(trim($application_token)) < 30) {
    $url_token = "https://trello.com/1/authorize?key=" . $key . "&name=My+Trello+Manager&expiration=never&response_type=token";
    die("Go to this URL to authorize:\n$url_token\n");
}

$application_token = trim($application_token);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function apiGet($path, $params = []) {
    global $key, $application_token;
    $params['key'] = $key;
    $params['token'] = $application_token;
    $url = "https://api.trello.com/1/$path?" . http_build_query($params);
    $response = @file_get_contents($url);
    if ($response === false) {
        die("API GET error: $url\n");
    }
    return json_decode($response, true);
}

function apiPost($path, $params = []) {
    global $key, $application_token;
    $params['key'] = $key;
    $params['token'] = $application_token;
    $url = "https://api.trello.com/1/$path";
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($params),
            'protocol_version' => '1.1',
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        die("API POST error: $url\n");
    }
    return json_decode($response, true);
}

function apiPut($path, $params = []) {
    global $key, $application_token;
    $params['key'] = $key;
    $params['token'] = $application_token;
    $url = "https://api.trello.com/1/$path";
    $context = stream_context_create([
        'http' => [
            'method' => 'PUT',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($params),
            'protocol_version' => '1.1',
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        die("API PUT error: $url\n");
    }
    return json_decode($response, true);
}

function getParam($name, $short = null) {
    global $argv;
    $search = "--$name";
    if ($short) {
        $search = "-$short";
    }
    $idx = array_search($search, $argv);
    if ($idx !== false && isset($argv[$idx + 1])) {
        return $argv[$idx + 1];
    }
    return null;
}

function hasFlag($name, $short = null) {
    global $argv;
    if (in_array("--$name", $argv)) return true;
    if ($short && in_array("-$short", $argv)) return true;
    return false;
}

function getCommand() {
    global $argv;
    if ($argc = count($argv) < 2) return null;
    return $argv[1];
}

function findBoard($nameHint = null) {
    $boards = apiGet('members/me/boards', ['fields' => 'name,id,closed']);
    if ($nameHint) {
        foreach ($boards as $b) {
            if (strcasecmp($b['name'], $nameHint) === 0) return $b;
        }
        // fuzzy match
        foreach ($boards as $b) {
            if (stripos($b['name'], $nameHint) !== false) return $b;
        }
        die("Board \"$nameHint\" not found. Available boards:\n" .
            implode("\n", array_map(function($b) { return '  - ' . $b['name']; }, $boards)) . "\n");
    }
    if (count($boards) === 1) return $boards[0];
    if (count($boards) === 0) die("No boards found.\n");
    die("Multiple boards found. Please specify --board <name>. Available:\n" .
        implode("\n", array_map(function($b) { return '  - ' . $b['name']; }, $boards)) . "\n");
}

function findList($boardId, $listName) {
    $lists = apiGet("boards/$boardId/lists", ['fields' => 'name,id']);
    foreach ($lists as $l) {
        if (strcasecmp($l['name'], $listName) === 0) return $l;
    }
    foreach ($lists as $l) {
        if (stripos($l['name'], $listName) !== false) return $l;
    }
    die("List \"$listName\" not found on board.\n");
}

function findCard($boardId, $listId, $cardTitle) {
    $cards = apiGet("boards/$boardId/cards", ['fields' => 'name,id,idList']);
    foreach ($cards as $c) {
        if ($c['idList'] !== $listId) continue;
        if (strcasecmp($c['name'], $cardTitle) === 0) return $c;
    }
    foreach ($cards as $c) {
        if ($c['idList'] !== $listId) continue;
        if (stripos($c['name'], $cardTitle) !== false) return $c;
    }
    // Try searching all lists if not found in specific list
    foreach ($cards as $c) {
        if (strcasecmp($c['name'], $cardTitle) === 0) return $c;
    }
    foreach ($cards as $c) {
        if (stripos($c['name'], $cardTitle) !== false) return $c;
    }
    die("Card \"$cardTitle\" not found on board.\n");
}

function findChecklist($cardId, $checklistName) {
    $checklists = apiGet("cards/$cardId/checklists");
    foreach ($checklists as $cl) {
        if (strcasecmp($cl['name'], $checklistName) === 0) return $cl;
    }
    foreach ($checklists as $cl) {
        if (stripos($cl['name'], $checklistName) !== false) return $cl;
    }
    die("Checklist \"$checklistName\" not found on card.\n");
}

function findChecklistItem($checklistId, $itemText) {
    $checklist = apiGet("checklists/$checklistId", ['fields' => 'name,id,checkItems']);
    foreach ($checklist['checkItems'] as $item) {
        if (strcasecmp($item['name'], $itemText) === 0) return $item;
    }
    foreach ($checklist['checkItems'] as $item) {
        if (stripos($item['name'], $itemText) !== false) return $item;
    }
    die("Item \"$itemText\" not found in checklist.\n");
}

function getPosParam() {
    $pos = getParam('pos');
    if ($pos === null) return null;
    $pos = strtolower($pos);
    if (!in_array($pos, ['top', 'bottom'])) {
        die("--pos must be 'top' or 'bottom'\n");
    }
    return $pos;
}

function logAction($message) {
    $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'trello-manager.log';
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function showUsage() {
    echo "Trello Manager - Manage your Trello boards from the command line\n\n";
    echo "Usage: php trello-manager.php <command> [options]\n\n";
    echo "Commands:\n";
    echo "  list-boards                              List all boards\n";
    echo "  list-lists    --board <name>              List lists on a board\n";
    echo "  list-cards    --board <name> --list <name> List cards in a list\n";
    echo "  add-card      --board <n> --list <n> --title <t> [--desc <d>] [--pos top|bottom]\n";
    echo "  add-checklist --board <n> --list <n> --card <t> --name <n> [--pos top|bottom]\n";
    echo "  add-item      --board <n> --list <n> --card <t> --checklist <n> --item <t> [--pos top|bottom]\n";
    echo "  move-item     --board <n> --list <n> --card <t> --checklist <n> --item <t> --pos top|bottom\n";
    echo "  check-item    --board <n> --list <n> --card <t> --checklist <n> --item <t>\n";
    echo "  uncheck-item  --board <n> --list <n> --card <t> --checklist <n> --item <t>\n";
    echo "  add-attachment --board <n> --list <n> --card <t> --url <u> [--name <f>]\n";
    echo "  set-desc      --board <n> --list <n> --card <t> --description <d>\n";
    echo "  rename-card   --board <n> --list <n> --card <t> --new-title <t>\n";
    echo "  move-card     --board <n> --list <n> --card <t> --to-list <n> [--pos top|bottom]\n";
    echo "  archive-card  --board <n> --list <n> --card <t>\n";
    echo "  query         --board <n> [--list <n>] [--card <t>]\n";
    echo "\n  Use --pos top|bottom to control where items are placed (default: bottom).\n";
    echo "  <n> = name, <t> = title/text, <d> = description, <u> = URL, <f> = filename\n";
}

// ─── Main ────────────────────────────────────────────────────────────────────

$command = getCommand();

switch ($command) {

    // ── List Boards ──────────────────────────────────────────────────────
    case 'list-boards':
        $boards = apiGet('members/me/boards', ['fields' => 'name,id,closed']);
        echo "Boards:\n";
        foreach ($boards as $b) {
            $closed = $b['closed'] ? ' [CLOSED]' : '';
            echo "  - {$b['name']}$closed\n";
        }
        logAction("Listed all boards");
        break;

    // ── List Lists ───────────────────────────────────────────────────────
    case 'list-lists':
        $board = findBoard(getParam('board'));
        $lists = apiGet("boards/{$board['id']}/lists", ['fields' => 'name,id,pos']);
        echo "Lists on \"{$board['name']}\":\n";
        foreach ($lists as $l) {
            $pos = isset($l['pos']) ? ' (pos: ' . $l['pos'] . ')' : '';
            echo "  - {$l['name']}{$pos}\n";
        }
        logAction("Listed lists on board \"{$board['name']}\"");
        break;

    // ── List Cards ───────────────────────────────────────────────────────
    case 'list-cards':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $cards = apiGet("lists/{$list['id']}/cards", ['fields' => 'name,id,shortUrl,pos']);
        echo "Cards in \"{$list['name']}\" on \"{$board['name']}\":\n";
        foreach ($cards as $c) {
            $pos = isset($c['pos']) ? ' (pos: ' . $c['pos'] . ')' : '';
            echo "  - {$c['name']} ({$c['shortUrl']}){$pos}\n";
        }
        logAction("Listed cards in list \"{$list['name']}\" on board \"{$board['name']}\"");
        break;

    // ── Add Card ─────────────────────────────────────────────────────────
    case 'add-card':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $title = getParam('title');
        if (!$title) die("--title is required\n");
        $desc = getParam('desc') ?: getParam('description') ?: '';
        $pos = getPosParam();
        $params = [
            'idList' => $list['id'],
            'name'   => $title,
            'desc'   => $desc,
        ];
        if ($pos) $params['pos'] = $pos;
        $result = apiPost('cards', $params);
        echo "Card created: {$result['name']} ({$result['shortUrl']})\n";
        logAction("Created card \"{$result['name']}\" in list \"{$list['name']}\" on board \"{$board['name']}\"");
        break;

    // ── Add Checklist ────────────────────────────────────────────────────
    case 'add-checklist':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $card = findCard($board['id'], $list['id'], getParam('card'));
        $name = getParam('name');
        if (!$name) die("--name is required\n");
        $pos = getPosParam();
        $params = ['name' => $name];
        if ($pos) $params['pos'] = $pos;
        $result = apiPost("cards/{$card['id']}/checklists", $params);
        echo "Checklist \"{$result['name']}\" added to card \"{$card['name']}\"\n";
        logAction("Added checklist \"{$result['name']}\" to card \"{$card['name']}\"");
        break;

    // ── Add Checklist Item ───────────────────────────────────────────────
    case 'add-item':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $card = findCard($board['id'], $list['id'], getParam('card'));
        $checklist = findChecklist($card['id'], getParam('checklist'));
        $itemText = getParam('item');
        if (!$itemText) die("--item is required\n");
        $pos = getPosParam();
        $params = ['name' => $itemText];
        if ($pos) $params['pos'] = $pos;
        $result = apiPost("checklists/{$checklist['id']}/checkItems", $params);
        echo "Item \"{$result['name']}\" added to checklist \"{$checklist['name']}\"\n";
        logAction("Added item \"{$result['name']}\" to checklist \"{$checklist['name']}\" on card \"{$card['name']}\"");
        break;

    // ── Check Item (mark complete) ───────────────────────────────────────
    case 'check-item':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $card = findCard($board['id'], $list['id'], getParam('card'));
        $checklist = findChecklist($card['id'], getParam('checklist'));
        $item = findChecklistItem($checklist['id'], getParam('item'));
        $result = apiPut("cards/{$card['id']}/checkItem/{$item['id']}", ['state' => 'complete']);
        echo "Item \"{$item['name']}\" marked as complete.\n";
        logAction("Checked item \"{$item['name']}\" in checklist \"{$checklist['name']}\" on card \"{$card['name']}\"");
        break;

    // ── Uncheck Item (mark incomplete) ───────────────────────────────────
    case 'uncheck-item':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $card = findCard($board['id'], $list['id'], getParam('card'));
        $checklist = findChecklist($card['id'], getParam('checklist'));
        $item = findChecklistItem($checklist['id'], getParam('item'));
        $result = apiPut("cards/{$card['id']}/checkItem/{$item['id']}", ['state' => 'incomplete']);
        echo "Item \"{$item['name']}\" marked as incomplete.\n";
        logAction("Unchecked item \"{$item['name']}\" in checklist \"{$checklist['name']}\" on card \"{$card['name']}\"");
        break;

    // ── Move Item (reposition checklist item) ───────────────────────────
    case 'move-item':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $card = findCard($board['id'], $list['id'], getParam('card'));
        $checklist = findChecklist($card['id'], getParam('checklist'));
        $item = findChecklistItem($checklist['id'], getParam('item'));
        $pos = getPosParam();
        if ($pos === null) die("--pos top|bottom is required\n");
        $result = apiPut("cards/{$card['id']}/checkItem/{$item['id']}", ['pos' => $pos]);
        echo "Item \"{$item['name']}\" repositioned to the $pos of checklist \"{$checklist['name']}\"\n";
        logAction("Moved item \"{$item['name']}\" to $pos of checklist \"{$checklist['name']}\" on card \"{$card['name']}\"");
        break;

    // ── Add Attachment ───────────────────────────────────────────────────
    case 'add-attachment':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $card = findCard($board['id'], $list['id'], getParam('card'));
        $url = getParam('url');
        if (!$url) die("--url is required\n");
        $params = ['url' => $url];
        $name = getParam('name');
        if ($name) $params['name'] = $name;
        $result = apiPost("cards/{$card['id']}/attachments", $params);
        echo "Attachment \"{$result['name']}\" added to card \"{$card['name']}\"\n";
        logAction("Added attachment \"{$result['name']}\" to card \"{$card['name']}\"");
        break;

    // ── Set Description ──────────────────────────────────────────────────
    case 'set-desc':
    case 'set-description':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $card = findCard($board['id'], $list['id'], getParam('card'));
        $desc = getParam('description') ?: getParam('desc');
        if ($desc === null) die("--description is required\n");
        $result = apiPut("cards/{$card['id']}", ['desc' => $desc]);
        echo "Description updated for card \"{$result['name']}\"\n";
        logAction("Updated description on card \"{$result['name']}\"");
        break;

    // ── Rename Card ──────────────────────────────────────────────────────
    case 'rename-card':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $card = findCard($board['id'], $list['id'], getParam('card'));
        $newTitle = getParam('new-title');
        if (!$newTitle) die("--new-title is required\n");
        $result = apiPut("cards/{$card['id']}", ['name' => $newTitle]);
        echo "Card renamed from \"{$card['name']}\" to \"{$result['name']}\"\n";
        logAction("Renamed card from \"{$card['name']}\" to \"{$result['name']}\"");
        break;

    // ── Move Card ────────────────────────────────────────────────────────
    case 'move-card':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $card = findCard($board['id'], $list['id'], getParam('card'));
        $toListName = getParam('to-list');
        if (!$toListName) die("--to-list is required\n");
        $toList = findList($board['id'], $toListName);
        $params = ['idList' => $toList['id']];
        $pos = getPosParam();
        if ($pos) $params['pos'] = $pos;
        $result = apiPut("cards/{$card['id']}", $params);
        echo "Card \"{$result['name']}\" moved to list \"{$toList['name']}\"\n";
        logAction("Moved card \"{$result['name']}\" to list \"{$toList['name']}\"");
        break;

    // ── Archive Card ─────────────────────────────────────────────────────
    case 'archive-card':
        $board = findBoard(getParam('board'));
        $list = findList($board['id'], getParam('list'));
        $card = findCard($board['id'], $list['id'], getParam('card'));
        $result = apiPut("cards/{$card['id']}", ['closed' => 'true']);
        echo "Card \"{$result['name']}\" archived.\n";
        logAction("Archived card \"{$result['name']}\"");
        break;

    // ── Query ────────────────────────────────────────────────────────────
    case 'query':
        $board = findBoard(getParam('board'));
        $boardData = apiGet("boards/{$board['id']}", [
            'lists' => 'all',
            'cards' => 'all',
            'checklists' => 'all',
            'fields' => 'name',
        ]);
        echo "Board: {$boardData['name']}\n\n";
        logAction("Queried board \"{$boardData['name']}\"");

        $listFilter = getParam('list');
        $cardFilter = getParam('card');

        foreach ($boardData['lists'] as $l) {
            if ($l['closed']) continue;
            if ($listFilter && stripos($l['name'], $listFilter) === false) continue;

            $listPos = isset($l['pos']) ? ' [pos: ' . $l['pos'] . ']' : '';
            echo "─── List: {$l['name']}{$listPos} ───\n";

            $listCards = array_filter($boardData['cards'], function($c) use ($l, $cardFilter) {
                if ($c['idList'] !== $l['id']) return false;
                if ($c['closed']) return false;
                if ($cardFilter && stripos($c['name'], $cardFilter) === false) return false;
                return true;
            });

            if (empty($listCards)) {
                echo "  (no cards)\n";
                continue;
            }

            foreach ($listCards as $c) {
                $cardPos = isset($c['pos']) ? ' [pos: ' . $c['pos'] . ']' : '';
                echo "  ■ {$c['name']}{$cardPos}\n";
                if (!empty($c['desc'])) {
                    $shortDesc = substr($c['desc'], 0, 100);
                    echo "    Description: $shortDesc\n";
                }
                if (!empty($c['attachments'])) {
                    echo "    Attachments: " . count($c['attachments']) . "\n";
                    foreach ($c['attachments'] as $a) {
                        echo "      - {$a['name']}: {$a['url']}\n";
                    }
                }
                // Find checklists for this card
                $cardChecklists = array_filter($boardData['checklists'], function($cl) use ($c) {
                    return $cl['idCard'] === $c['id'];
                });
                foreach ($cardChecklists as $cl) {
                    echo "    Checklist: {$cl['name']}\n";
                    foreach ($cl['checkItems'] as $item) {
                        $checked = $item['state'] === 'complete' ? '☑' : '☐';
                        $itemPos = isset($item['pos']) ? ' [pos: ' . $item['pos'] . ']' : '';
                        echo "      $checked {$item['name']}{$itemPos}\n";
                    }
                }
                echo "\n";
            }
        }
        break;

    default:
        echo "Unknown command: $command\n\n";
        showUsage();
        exit(1);
}
