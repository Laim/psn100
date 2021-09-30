<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
ini_set("default_socket_timeout", "6000");
set_time_limit(0);
require_once("../init.php");

if (isset($_POST["trophy"])) {
    $trophyId = $_POST["trophy"];
    $status = $_POST["status"];
    $trophies = explode(",", $trophyId);

    $trophyNames = array();
    $trophyGroups = array();
    $trophyTitles = array();
    foreach ($trophies as $trophyId) {
        $database->beginTransaction();
        $query = $database->prepare("UPDATE trophy SET status = :status WHERE id = :trophy_id");
        $query->bindParam(":status", $status, PDO::PARAM_INT);
        $query->bindParam(":trophy_id", $trophyId, PDO::PARAM_INT);
        $query->execute();
        $database->commit();

        $query = $database->prepare("SELECT np_communication_id, group_id, name FROM trophy WHERE id = :trophy_id");
        $query->bindParam(":trophy_id", $trophyId, PDO::PARAM_INT);
        $query->execute();
        $trophy = $query->fetch();
        array_push($trophyNames, $trophyId ." (". $trophy["name"] .")");
        array_push($trophyGroups, $trophy["np_communication_id"].",".$trophy["group_id"]);
        array_push($trophyTitles, $trophy["np_communication_id"]);
    }
    $trophyGroups = array_unique($trophyGroups);
    $trophyTitles = array_unique($trophyTitles);

    // Recalculate trophies for trophy group
    foreach ($trophyGroups as $trophyGroup) {
        $explode = explode(",", $trophyGroup);
        $trophy["np_communication_id"] = $explode[0];
        $trophy["group_id"] = $explode[1];
        
        $query = $database->prepare("SELECT type, COUNT(*) AS count FROM trophy WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND status = 0 GROUP BY type");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
        $query->execute();
        $trophyTypes = $query->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!isset($trophyTypes["bronze"])) {
            $trophyTypes["bronze"] = 0;
        }
        if (!isset($trophyTypes["silver"])) {
            $trophyTypes["silver"] = 0;
        }
        if (!isset($trophyTypes["gold"])) {
            $trophyTypes["gold"] = 0;
        }
        if (!isset($trophyTypes["platinum"])) {
            $trophyTypes["platinum"] = 0;
        }
        $database->beginTransaction();
        $query = $database->prepare("UPDATE trophy_group SET bronze = :bronze, silver = :silver, gold = :gold, platinum = :platinum WHERE np_communication_id = :np_communication_id AND group_id = :group_id");
        $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
        $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
        $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
        $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
        $query->execute();
        $database->commit();

        // Recalculate stats for trophy group for all the affected players
        $maxScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
        $players = $database->prepare("SELECT account_id FROM trophy_group_player WHERE np_communication_id = :np_communication_id AND group_id = :group_id");
        $players->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $players->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
        $players->execute();
        while ($player = $players->fetch()) {
            $query = $database->prepare("SELECT type, COUNT(type) AS count FROM trophy_earned te LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id AND t.group_id = te.group_id AND t.order_id = te.order_id AND t.status = 0 WHERE account_id = :account_id AND te.np_communication_id = :np_communication_id AND te.group_id = :group_id GROUP BY type");
            $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
            $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
            $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
            $query->execute();
            $trophyTypes = $query->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!isset($trophyTypes["bronze"])) {
                $trophyTypes["bronze"] = 0;
            }
            if (!isset($trophyTypes["silver"])) {
                $trophyTypes["silver"] = 0;
            }
            if (!isset($trophyTypes["gold"])) {
                $trophyTypes["gold"] = 0;
            }
            if (!isset($trophyTypes["platinum"])) {
                $trophyTypes["platinum"] = 0;
            }
            $userScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
            if ($maxScore == 0) {
                $progress = 100;
            } else {
                $progress = floor($userScore/$maxScore*100);
                if ($userScore != 0 && $progress == 0) {
                    $progress = 1;
                }
            }
            $database->beginTransaction();
            $query = $database->prepare("UPDATE trophy_group_player SET bronze = :bronze, silver = :silver, gold = :gold, platinum = :platinum, progress = :progress WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND account_id = :account_id");
            $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
            $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
            $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
            $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
            $query->bindParam(":progress", $progress, PDO::PARAM_INT);
            $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
            $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
            $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
            $query->execute();
            $database->commit();
        }
    }

    // Recalculate trophies for trophy title
    foreach ($trophyTitles as $trophyTitle) {
        $trophy["np_communication_id"] = $trophyTitle;
        
        $query = $database->prepare("SELECT SUM(bronze) AS bronze, SUM(silver) AS silver, SUM(gold) AS gold, SUM(platinum) AS platinum FROM trophy_group WHERE np_communication_id = :np_communication_id");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->execute();
        $trophies = $query->fetch();
        $database->beginTransaction();
        $query = $database->prepare("UPDATE trophy_title SET bronze = :bronze, silver = :silver, gold = :gold, platinum = :platinum WHERE np_communication_id = :np_communication_id");
        $query->bindParam(":bronze", $trophies["bronze"], PDO::PARAM_INT);
        $query->bindParam(":silver", $trophies["silver"], PDO::PARAM_INT);
        $query->bindParam(":gold", $trophies["gold"], PDO::PARAM_INT);
        $query->bindParam(":platinum", $trophies["platinum"], PDO::PARAM_INT);
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->execute();
        $database->commit();

        // Recalculate stats for trophy title for the affected players
        $maxScore = $trophies["bronze"]*15 + $trophies["silver"]*30 + $trophies["gold"]*90; // Platinum isn't counted for
        $players = $database->prepare("SELECT account_id FROM trophy_title_player WHERE np_communication_id = :np_communication_id");
        $players->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $players->execute();
        while ($player = $players->fetch()) {
            $query = $database->prepare("SELECT
                    IFNULL(SUM(bronze),
                    0) AS bronze,
                    IFNULL(SUM(silver),
                    0) AS silver,
                    IFNULL(SUM(gold),
                    0) AS gold,
                    IFNULL(SUM(platinum),
                    0) AS platinum
                FROM
                    trophy_group_player tgp
                WHERE
                    account_id = :account_id AND tgp.np_communication_id = :np_communication_id");
            $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
            $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
            $query->execute();
            $trophyTypes = $query->fetch();
            $userScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
            if ($maxScore == 0) {
                $progress = 100;
            } else {
                $progress = floor($userScore/$maxScore*100);
                if ($userScore != 0 && $progress == 0) {
                    $progress = 1;
                }
            }
            $database->beginTransaction();
            $query = $database->prepare("UPDATE trophy_title_player SET bronze = :bronze, silver = :silver, gold = :gold, platinum = :platinum, progress = :progress WHERE np_communication_id = :np_communication_id AND account_id = :account_id");
            $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
            $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
            $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
            $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
            $query->bindParam(":progress", $progress, PDO::PARAM_INT);
            $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
            $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
            $query->execute();
            $database->commit();
        }

        // // Add all affected players to the queue to recalculate trophy count, level and level progress
        // $query = $database->prepare("INSERT IGNORE
        //     INTO player_queue(online_id, request_time)
        //     SELECT
        //         online_id,
        //         '2030-12-24 00:00:00'
        //     FROM
        //         player p
        //     WHERE p.status = 0 AND EXISTS
        //         (
        //         SELECT
        //             1
        //         FROM
        //             trophy_title_player ttp
        //         WHERE
        //             ttp.np_communication_id = :np_communication_id AND ttp.account_id = p.account_id
        //     )");
        // $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        // $query->execute();
    }

    if ($status == 1) {
        $statusText = "unobtainable";
    } else {
        $statusText = "obtainable";
    }

    $success = "<p>";
    foreach ($trophyNames as $trophyName) {
        $success .= "Trophy ID ". $trophyName ."<br>";
    }
    $success .= "is now set as ". $statusText ."</p>";
}

?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Unobtainable Trophy</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <form method="post" autocomplete="off">
            Trophy ID:<br>
            <input type="text" name="trophy"><br>
            Status:<br>
            <select name="status">
                <option value="1">Unobtainable</option>
                <option value="0">Obtainable</option>
            </select><br><br>
            <input type="submit" value="Submit">
        </form>

        <?php
        if (isset($success)) {
            echo $success;
        }
        ?>
    </body>
</html>
