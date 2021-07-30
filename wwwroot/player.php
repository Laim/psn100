<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$query = $database->prepare("SELECT *
    FROM   player
    WHERE  account_id = :account_id ");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$player = $query->fetch();

$query = $database->prepare("SELECT COUNT(*) FROM trophy_title_player ttp JOIN trophy_title tt USING (np_communication_id) WHERE tt.status = 0 AND ttp.account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$numberOfGames = $query->fetchColumn();

$metaData = new stdClass();
$metaData->title = $player["online_id"] ."'s Trophy Progress";
$metaData->description = "Level ". $player["level"] .".". $player["progress"] ." ~ ". $numberOfGames ." Unique Games ~ ". $player["platinum"] ." Unique Platinums";
$metaData->image = "https://psn100.net/img/avatar/". $player["avatar_url"];
$metaData->url = "https://psn100.net/player/". $player["online_id"];

$title = $player["online_id"] . "'s Trophy Progress ~ PSN 100%";
require_once("player_header.php");

$url = $_SERVER["REQUEST_URI"];
$url_parts = parse_url($url);
// If URL doesn't have a query string.
if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
    parse_str($url_parts["query"], $params);
} else {
    $params = array();
}

$queryText = "SELECT Count(*)
    FROM   trophy_title_player ttp
           JOIN trophy_title tt USING (np_communication_id)
    WHERE  tt.status != 2
           AND ttp.account_id = :account_id ";
if (isset($_GET["filter"]) && $_GET["filter"] == "incomplete") {
    $queryText .= " AND ttp.progress != 100 ";
}
$query = $database->prepare($queryText);
$query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
$query->execute();
$gameCount = $query->fetchColumn();

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 50;
$offset = ($page - 1) * $limit;
?>
        <div class="row">
            <div class="col-2 text-center">
                <h5>Games</h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/log">Log</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/game?sort=completion&player=<?= $player["online_id"]; ?>">Game Advisor</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/timeline">Timeline</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/random">Random Games</a></h5>
            </div>
        </div>

        <div class="row">
            <div class="col-6 text-center">
                <b>Filter</b><br>
                <?php
                unset($params["filter"]);
                $httpQuery = "?". http_build_query($params);
                ?>
                <a href="<?= ($httpQuery == "?") ? $player["online_id"] : $httpQuery; ?>">Default</a>
                 ~
                <?php
                $params["filter"] = "incomplete";
                ?>
                <a href="?<?= http_build_query($params); ?>">Not 100%</a>
                <?php
                $params["filter"] = isset($_GET["filter"]) ? $_GET["filter"] : null;
                ?>
            </div>
            <div class="col-6 text-center">
                <b>Order By</b><br>
                <?php
                unset($params["sort"]);
                $httpQuery = "?". http_build_query($params);
                ?>
                <a href="<?= ($httpQuery == "?") ? $player["online_id"] : $httpQuery; ?>">Default</a>
                 ~
                <?php
                $params["sort"] = "rarity";
                ?>
                <a href="?<?= http_build_query($params); ?>">Rarity</a>
                <?php
                $params["sort"] = isset($_GET["sort"]) ? $_GET["sort"] : null;
                ?>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <table class="table table-responsive table-striped">
                    <tr class="table-primary">
                        <th scope="col">Icon</th>
                        <th scope="col" width="100%">Game Title</th>
                        <th scope="col">Platform</th>
                        <th scope="col" class="text-center"><img src="/img/playstation/trophies.png" alt="Trophies" width="50" /></th>
                    </tr>

                    <?php
                    if ($player["status"] == 3) {
                        ?>
                        <tr>
                            <td colspan="4" class="text-center"><h3>This player seems to have a <a href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile.</h3></td>
                        </tr>
                        <?php
                    } else {
                        if (isset($_GET["sort"])) {
                            $queryText = "SELECT tt.id,
                                       tt.np_communication_id,
                                       tt.name,
                                       tt.icon_url,
                                       tt.platform,
                                       tt.status,
                                       ttp.bronze,
                                       ttp.silver,
                                       ttp.gold,
                                       ttp.platinum,
                                       ttp.progress,
                                       ttp.last_updated_date,
                                       ttp.rarity_points
                                FROM   trophy_title_player ttp
                                       JOIN trophy_title tt USING (np_communication_id)
                                WHERE  ttp.account_id = :account_id
                                       AND tt.status != 2 ";
                            if (isset($_GET["filter"]) && $_GET["filter"] == "incomplete") {
                                $queryText .= " AND ttp.progress != 100 ";
                            }
                            $queryText .= " GROUP  BY np_communication_id
                                ORDER  BY rarity_points DESC,
                                          name
                                LIMIT  :offset, :limit ";
                            $query = $database->prepare($queryText);
                        } else {
                            $queryText = "SELECT tt.id,
                                       tt.np_communication_id,
                                       tt.name,
                                       tt.icon_url,
                                       tt.platform,
                                       tt.status,
                                       ttp.bronze,
                                       ttp.silver,
                                       ttp.gold,
                                       ttp.platinum,
                                       ttp.progress,
                                       ttp.last_updated_date,
                                       ttp.rarity_points
                                FROM   trophy_title_player ttp
                                       JOIN trophy_title tt USING(np_communication_id)
                                WHERE  ttp.account_id = :account_id
                                       AND tt.status != 2 ";
                            if (isset($_GET["filter"]) && $_GET["filter"] == "incomplete") {
                                $queryText .= " AND ttp.progress != 100 ";
                            }
                            $queryText .= " GROUP  BY np_communication_id
                                ORDER  BY last_updated_date DESC
                                LIMIT  :offset, :limit ";
                            $query = $database->prepare($queryText);
                        }
                        $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                        $query->bindParam(":offset", $offset, PDO::PARAM_INT);
                        $query->bindParam(":limit", $limit, PDO::PARAM_INT);
                        $query->execute();
                        $playerGames = $query->fetchAll();

                        foreach ($playerGames as $playerGame) {
                            $trClass = "";
                            if ($playerGame["status"] == 1) {
                                $trClass = " class=\"table-warning\" title=\"This game is delisted, no trophies will be accounted for on any leaderboard.\"";
                            } elseif ($playerGame["status"] == 3) {
                                $trClass = " class=\"table-warning\" title=\"This game is obsolete, no trophies will be accounted for on any leaderboard.\"";
                            } elseif ($playerGame["progress"] == 100) {
                                $trClass = " class=\"table-success\"";
                            } ?>
                            <tr<?= $trClass; ?>>
                                <td scope="row">
                                    <a href="/game/<?= $playerGame["id"] ."-". slugify($playerGame["name"]); ?>/<?= $player["online_id"]; ?>">
                                        <img src="/img/title/<?= $playerGame["icon_url"]; ?>" alt="" style="background: linear-gradient(to bottom,#145EBB 0,#142788 100%);" width="100" />
                                    </a>
                                </td>
                                <td>
                                    <a href="/game/<?= $playerGame["id"] ."-". slugify($playerGame["name"]); ?>/<?= $player["online_id"]; ?>">
                                        <?= htmlentities($playerGame["name"]); ?>
                                    </a>
                                    <br>
                                    <?= $playerGame["last_updated_date"]; ?>
                                    <?php
                                    if ($playerGame["progress"] == 100) {
                                        $query = $database->prepare("SELECT Min(earned_date) AS first_trophy,
                                                   Max(earned_date) AS last_trophy
                                            FROM   trophy_earned
                                            WHERE  np_communication_id = :np_communication_id
                                                   AND account_id = :account_id ");
                                        $query->bindParam(":np_communication_id", $playerGame["np_communication_id"], PDO::PARAM_STR);
                                        $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                                        $query->execute();
                                        $completionDates = $query->fetch();
                                        $datetime1 = date_create($completionDates["first_trophy"]);
                                        $datetime2 = date_create($completionDates["last_trophy"]);
                                        $completionTimes = explode(", ", date_diff($datetime1, $datetime2)->format("%y years, %m months, %d days, %h hours, %i minutes, %s seconds")); ?>
                                        <br>
                                        <?php
                                        $first = -1;
                                        $second = -1;
                                        for ($i = 0; $i < count($completionTimes); $i++) {
                                            if ($completionTimes[$i][0] == "0") {
                                                continue;
                                            }

                                            if ($first == -1) {
                                                $first = $i;
                                            } elseif ($second == -1) {
                                                $second = $i;
                                            }
                                        }

                                        if ($first >= 0 && $second >= 0) {
                                            echo "Completed in ". $completionTimes[$first] .", ". $completionTimes[$second];
                                        } elseif ($first >= 0 && $second == -1) {
                                            echo "Completed in ". $completionTimes[$first];
                                        }
                                    } ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    foreach (explode(",", $playerGame["platform"]) as $platform) {
                                        echo "<span class=\"badge badge-pill badge-primary\">" . $platform . "</span> ";
                                    } ?>
                                </td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <?= $playerGame["bronze"]; ?> <img src="/img/playstation/bronze.png" alt="Bronze" width="24" />
                                    <?= $playerGame["silver"]; ?> <img src="/img/playstation/silver.png" alt="Silver" width="24" />
                                    <?= $playerGame["gold"]; ?> <img src="/img/playstation/gold.png" alt="Gold" width="24" />
                                    <?= $playerGame["platinum"]; ?> <img src="/img/playstation/platinum.png" alt="Platinum" width="24" />
                                    <br>
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $playerGame["progress"]; ?>%;" aria-valuenow="<?= $playerGame["progress"]; ?>" aria-valuemin="0" aria-valuemax="100"><?= $playerGame["progress"]; ?>%</div>
                                    </div>
                                    <?php
                                    if ($player["status"] == 0 && $playerGame["status"] == 0) {
                                        echo number_format($playerGame["rarity_points"]) ." Rarity Points";
                                    } elseif ($playerGame["status"] == 1) {
                                        echo "<span class=\"badge badge-pill badge-warning\">Delisted</span>";
                                    } elseif ($playerGame["status"] == 3) {
                                        echo "<span class=\"badge badge-pill badge-warning\">Obsolete</span>";
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </table>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php
                        if ($page > 1) {
                            $params["page"] = $page - 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">Prev</a></li>
                            <?php
                        }

                        if ($page > 3) {
                            $params["page"] = 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">1</a></li>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <?php
                        }

                        if ($page-2 > 0) {
                            $params["page"] = $page - 2; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page-2; ?></a></li>
                            <?php
                        }

                        if ($page-1 > 0) {
                            $params["page"] = $page - 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page-1; ?></a></li>
                            <?php
                        }
                        ?>

                        <?php
                        $params["page"] = $page;
                        ?>
                        <li class="page-item active" aria-current="page"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page; ?></a></li>

                        <?php
                        if ($page+1 < ceil($gameCount / $limit)+1) {
                            $params["page"] = $page + 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page+1; ?></a></li>
                            <?php
                        }

                        if ($page+2 < ceil($gameCount / $limit)+1) {
                            $params["page"] = $page + 2; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page+2; ?></a></li>
                            <?php
                        }

                        if ($page < ceil($gameCount / $limit)-2) {
                            $params["page"] = ceil($gameCount / $limit); ?>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= ceil($gameCount / $limit); ?></a></li>
                            <?php
                        }

                        if ($page < ceil($gameCount / $limit)) {
                            $params["page"] = $page + 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">Next</a></li>
                            <?php
                        }
                        ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</main>
<?php
require_once("footer.php");
?>
