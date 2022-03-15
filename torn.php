<?php

    // Saves API key in cookie, still needs some work
    if($_POST['saveKey'])
    {
        $api = $_POST['key'];
        setcookie("key", "$api");
        $_COOKIE['key'] = $api;
    }
    else if(isset($_COOKIE['key']))
    {
        $api = $_COOKIE['key'];
    }
    else 
    {
        $api = $_POST['key'];
        setcookie("key", "", 0, "/", true);
    }    
       
    // Form for tool selection and API input
    include("tornform.php");
    // Re-usable API class for making calls and error handling
    require_once('resources/tornapi.php');
    $apiConnection = new TornAPI();

    /* 
     * TOOL: Show next stock to purchase based on owned stocks
     *  Creates a table that hides already owned increments
     * and orders remaining increments by highest to lowest ROI
     * and highlights stocks that can currently be purchased with
     * funds on hand and in vault
     */
    if($_POST['function'] == "nextStock")
    {
        // First call pulls user owned stocks, second pulls general stock info, 
        // third call pulls information on items that stocks pay out
        $tornData = $apiConnection->apiCall($api, "torn", "stocks");
        $userData = $apiConnection->apiCall($api, "user", "stocks");
        $itemData = $apiConnection->apiCall($api,"torn", "items","364,365,366,367,368,366,369,370,554,817,818");

        // Set currency to USD
        setlocale(LC_MONETARY, 'en_US');
        
        // Total liquid cash available
        $moneyOnHand = $userData['money_onhand'];
        $moneyInVault = $userData['vault_amount'];            
        $totalCash = $moneyInVault + $moneyOnHand;

        // Loop through each stock to find those with active benefits        
        foreach($tornData['stocks'] as $stocks)
        {   
            // Check to see if stock has an active payout
            if($stocks['benefit']['type'] == 'active')
            {    
                // assign stock data from API to variables
                $key = $stocks['stock_id'];
                $stockName = $stocks['name'];
                $stockID = $stocks['stock_id'];
                $stockPrice = $stocks['current_price'];
                $benefitBlockShares = $stocks['benefit']['requirement'];
                $benefitBlockCost = $stockPrice * $benefitBlockShares;
                $sharesOwned = $userData['stocks'][$key]['total_shares'];
                $frequency = $tornData['stocks'][$key]['benefit']['frequency'];
                
                // Amount of payouts per year
                if($frequency == 7)
                {
                    $yearlyPayouts = 52;
                }
                else
                {
                    $yearlyPayouts = 12;
                }

                // remove formatting/prefixes from benefits to prepare for calculations
                $benefit = $stocks['benefit']['description'];
                $dividendValue = preg_replace('/\$|\,+|1x\s+/', '', $benefit);

                // Check if dividend is an item,
                // if it is, set dividend value to item value
                if(!is_numeric($dividendValue))
                {                
                    foreach($itemData['items'] as $item)
                    {                    
                        if($item['name'] == $dividendValue)
                        {
                            $dividendValue = $item['market_value'];
                            break;
                        }
                        //McSmoogle - Valued at 4x 25 energy cans
                        else if($stockID == 29)
                        {
                            $dividendValue = $itemData['items']['554']['market_value'] * 4;
                            break;
                        }
                        //Evil Duck - valued at tiny number to avoid division by 0 error later(alternatively value at equivalant happy from EDVD, still considering
                        else if($stockID == 28)
                        {
                            $dividendValue = 0.0000000000000001; //$itemData['items']['366']['market_value'] * 0.4;
                            break;
                        }
                    }
                }

                // Check for player's current increment, calculate cost/requirements of next block
                if($userData['stocks'][$key]['dividend']['increment'])
                {            
                    $currentIncrement = $userData['stocks'][$key]['dividend']['increment'];

                    // Calculate next increments exponential shares/money required for BB
                    $nextIncrement = pow(2,($currentIncrement));
                    $nextBlockCost = $nextIncrement * $benefitBlockCost;    
                    $nextBlockShares = $nextIncrement * $benefitBlockShares;

                    $nextIncrement = $currentIncrement+1; // Display value for next incremenet                
                }
                // if no blocks owned, calculate first block cost
                else           
                {   
                    $currentIncrement = 0;
                    $nextBlockCost = $benefitBlockCost;
                    $nextIncrement = 1;
                    $nextBlockShares = $benefitBlockShares;
                }

                // set value of non-item/money/irregular value active payouts to 0
                if(is_numeric($dividendValue))
                {
                $yearlyTotal = $yearlyPayouts * $dividendValue;
                $roi = ($yearlyTotal / $nextBlockCost) * 100;
                }
                else
                {
                    $yearlyTotal = 0;
                    $roi = 0;
                }

                // Calculate value/increments of player's currently held stocks
                if($currentIncrement > 0)
                {

                    $multiplier = 0;
                    $totalShares = 0;

                    // Calculating cost remaining to aqcuire next increment
                    for($i = 0; $i < $currentIncrement; $i++)
                    {
                        $multiplier = pow(2,$i);
                        $totalShares += $multiplier * $benefitBlockShares;
                    }                   
                    // Calculate shares above current increment to determine progress to next block
                    $sharesToNextBlock = ($nextBlockShares +$totalShares) - $sharesOwned;
                    $costToNextBlock = $stockPrice * $sharesToNextBlock;
                }
                else        // If no increments owned, use values for first increment
                {
                    $sharesToNextBlock = $benefitBlockShares - $sharesOwned;
                    $costToNextBlock = $sharesToNextBlock * $stockPrice;
                }

                // highlights row if player can afford with funds in vault and cash on hand
                if($costToNextBlock <= $totalCash)
                {
                    $rowColor = "<tr bgcolor='lime'>";
                }
                else
                {
                    $rowColor = "<tr>";
                }

                // Row template for table
                $rowString = "$rowColor<td>" . $stockName . "</td><td align='center'>" . $nextIncrement . "</td><td align='right'>$" 
                        . number_format($nextBlockCost) . "</td><td align='center'>$" . number_format($yearlyTotal) . "</td><td>" 
                        . $benefit . "</td><td align='right'>". number_format($roi, 2) . "%</td>"
                        . "<td align='right'>" . number_format($nextBlockShares)
                        . "</td><td align='right'>" . number_format($sharesToNextBlock) . "</td><td>$" . number_format($costToNextBlock)
                        . "</td><td align='right'><a href='https://www.torn.com/page.php?sid=stocks&stockID=$key&tab=dividend' target='_blank'>Click to Buy</a></td></tr>";

                // array for sorting by ROI containing HTML for each row
                $rowData[] = ["rowString" => $rowString, "roi" => $roi];                     
            }
        }

        // Sort array by ROI and places rows into a string to be used in the table
        array_multisort(array_column($rowData, "roi"), SORT_DESC, $rowData);    
        foreach($rowData as $row)
        {
            $tableData .= $row['rowString'];
        }

        echo "<p><table border='1' id='nextStocksTable'>"
                . "<tr><th>Stock Name</th><th>Next Increment</th><th>Cost of BB</th>"
                . "<th>Annual Return</th><th>Benefit</th>"
                . "<th>Annual ROI</th><th>BB Share Amount</td>"
                . "<th>Shares Needed</th><th>Money Needed</th><th>Link</tr>"
                . "$tableData</table></p>"
                . "<p><table border='1'><tr bgcolor='lime'><td>Green rows are stock blocks you can afford with funds in vault and on hand</td></tr></table>";
    }
    // TOOL: Show all stocks sorted by ROI, highlight those already owned in green
    // and those partially owned in yellow. Sorts by ROI highest to lowest
    else if($_POST['function'] == "allStocks")
    {
        // First call pulls user owned stocks, second pulls general stock info, 
        // third call pulls information on items that stocks pay out
        $userData = $apiConnection->apiCall($api,"user","stocks");
        $tornData = $apiConnection->apiCall($api,"torn", "stocks",);
        $itemData = $apiConnection->apiCall($api,"torn", "items","364,365,366,367,368,366,369,370,554,817,818");

        // Set currency to USD
        setlocale(LC_MONETARY, 'en_US');

        // Loop through each stock to find those with active benefits   
        foreach($tornData['stocks'] as $stocks)
        {   
            if($stocks['benefit']['type'] == 'active')
            {    
                // assign stock API info to variables
                $key = $stocks['stock_id'];
                $stockName = $stocks['name'];
                $stockID = $stocks['stock_id'];
                $stockPrice = $stocks['current_price'];
                $benefitBlockShares = $stocks['benefit']['requirement'];
                $benefitBlockCost = $stockPrice * $benefitBlockShares;
                $sharesOwned = $userData['stocks'][$key]['total_shares'];
                $frequency = $tornData['stocks'][$key]['benefit']['frequency'];

                // Amount of payouts per year
                if($frequency == 7)
                {
                    $yearlyPayouts = 52;
                }
                else
                {
                    $yearlyPayouts = 12;
                }

                // remove formatting/prefixes from benefits for use in calculations
                $benefit = $stocks['benefit']['description'];
                $dividendValue = preg_replace('/\$|\,+|1x\s+/', '', $benefit);

                // Check if dividend is an item, if so, set to value of item payout
                if(!is_numeric($dividendValue))
                {                
                    foreach($itemData['items'] as $item)
                    {                    
                        if($item['name'] == $dividendValue)
                        {
                            $dividendValue = $item['market_value'];
                            break;
                        }
                        //McSmoogle - valued at 4x 25 energy drinks
                        else if($stockID == 29)
                        {
                            $dividendValue = $itemData['items']['554']['market_value'] * 4;
                            break;
                        }
                        //Evil Duck - value set to tiny number to avoid division by zero error(alternatively valued at equivalent, still considering)
                        else if($stockID == 28)
                        {
                            $dividendValue = 0.0000000000000001; //$itemData['items']['366']['market_value'] * 0.4;
                            break;
                        }
                    }
                }
                
                // Determine current increment and partial blocks owned
                if($userData['stocks'][$key]['dividend']['increment'])
                {
                    $totalShares = 0;
                    $currentIncrement = ($userData['stocks'][$key]['dividend']['increment']);

                    // Determine total shares required for current increment of increments 
                    for($i = 0; $i < $currentIncrement; $i++)
                    {
                        $multiplier = pow(2,$i);
                        $totalShares += $multiplier * $benefitBlockShares;
                    }           
                    
                    // Calculate shares in excess of current incremement
                    $sharesToNextBlock = $sharesOwned - $totalShares;
                }
                else
                {
                    $currentIncrement = 0;
                }
               
                // Calculate values(total cost, shares, ROI) for up to 4 increments
                for($i = 1; $i <= 4; $i++)      
                {    
                    $nextIncrement = $i;         

                    if(is_numeric($dividendValue))
                    {
                        $yearlyTotal = $yearlyPayouts * $dividendValue;
                        $roi = (($yearlyTotal / $benefitBlockCost) * 100)/($nextIncrement);

                        $multiplier = pow(2,$nextIncrement-1);
                        $nextBlockCost = $multiplier * $benefitBlockCost; 
                        $nextBlockShares = $multiplier * $benefitBlockShares;
                    }
                    else // handling for non-money/item benefits, set to 0
                    {
                        $yearlyTotal = 0;
                        $roi = 0;
                    }

                    // Row highlighting for owned and partially owned blocks
                    if($i <= $currentIncrement)
                    {
                        $rowColor = "<tr bgcolor='lime'>"; 
                    }
                    else if(($currentIncrement == 0 && $sharesOwned > 0 && $nextIncrement==1) || ($currentIncrement == $nextIncrement-1 && $sharesToNextBlock > 0))
                    {
                        $rowColor = "<tr bgcolor='yellow'>"; 
                        $sharesToNextBlock = 0;
                    }
                    else
                    {
                        $rowColor = "<tr>";
                    }

                    // Row template for table
                    $rowString = $rowColor . "<td>" . $stockName . "</td><td align='center'>" . $nextIncrement . "</td><td align='right'>" 
                            . number_format($nextBlockShares) . "</td><td align='center'>$" 
                            . number_format($nextBlockCost) . "</td><td>$" 
                            . number_format($yearlyTotal) . "</td><td>" 
                            . $benefit . "</td><td align='right'>". number_format($roi, 2) . "%</td>"                   
                            . "</td><td align='right'><a href='https://www.torn.com/page.php?sid=stocks&stockID=$key&tab=owned' target='_blank'>Click to Buy</a></td></tr>";

                    // array for sorting by ROI, holds row HTML and ROI
                    $rowData[] = ["rowString" => $rowString, "roi" => $roi];          

                }
            }
        }

        // Sort array and add HTML to string for use in table
        array_multisort(array_column($rowData, "roi"), SORT_DESC, $rowData);    
        foreach($rowData as $row)
        {
            $tableData .= $row['rowString'];
        }

        echo "<p><table border='1'><tr bgcolor='lime'><td>Green rows are stock increments you already own</td></tr>"
            . "<tr bgcolor='yellow'><td>Yellow rows are increments that you own some shares, but not enough for the full block</td></tr><table>"
            . "<p><table border='1' id='allStocksTable'>"
            . "<tr><th>Stock Name</th><th>Next Increment</th>"
            . "<th>BB Share Amount</td><th>Cost of BB</th>"
            . "<th>Annual Return</th><th>Benefit</th>"
            . "<th>Annual ROI</th>"
            . "<th>Link</tr>"
            . "$tableData</table></p>";
    }
    
    /* TOOL: Determines total remaining education time
     *  Calculates current education reduction bonuses and 
     * adds up total time of uncompleted courses and currently enrolled course
     * provides approximate date of when every single education course will be
     * completed, assuming there are no large gaps between a course ending and
     * enrolling in a new one.
     */    
    else if($_POST['function'] == "educationLeft")
    {
        // First call pulls user information such as completed courses 
        // and current education reduction bonuses, second call pulls
        // general course information      
        $userData = $apiConnection->apiCall($api,"user", "education,merits,perks");        
        $tornData = $apiConnection->apiCall($api,"torn", "education");      

        // calculate total base time to complete ALL courses
        foreach($tornData['education'] as $course)
        {
            $timeToCompleteAll += $course['duration'];
        }

        // Subtracts duration of completed courses from total time
         foreach($userData['education_completed'] as $courseCompleted)
         {  
             if(isset($courseCompleted))
             {
                 $timeToCompleteAll -= $tornData['education']["$courseCompleted"]['duration'];
             }        
         }

        // If time remaining is 0, all educations are complete
        if($timeToCompleteAll == 0)
        {
            echo "You have already completed all of your educations!";
        }
        else // Calculate education reduction bonuses
        {
            $jobReduction = 0;
            $stockReduction = 0;
            $meritReduction = 0;                

            // Check for principal perk
            foreach($userData['job_perks'] as $principalPerk)
            {
                if($principalPerk == "- 10% Education length")
                    {
                        $jobReduction = 0.1;
                    }
            }       
            // Check for WSSB perk
            foreach($userData['stock_perks'] as $stockPerk)
            {
                if($stockPerk == "10% Course Time Reduction (WSU)")
                    {
                        $stockReduction = 0.1;
                    }
            }
            // Check for merits
            if($userData['merits']["Education Length"] > 0)
            {
                $meritReduction = $userData['merits']["Education Length"] * 0.02;
            }

            // Calculate total education percent reduction and remaining time with bonuses
            $totalReduction = ((1 - $jobReduction) * (1 - $stockReduction) * (1 - $meritReduction));
            $timeToCompleteAll *= $totalReduction;

            // subtracts currently enrolled course's completed time from total education
            $currentCourse = $userData['education_current'];
            $timeToCompleteAll -= (($tornData['education'][$currentCourse]['duration']*$totalReduction) - $userData['education_timeleft']);            

            // find timestamp for completion date and format output
            $dateCompleted = time() + $timeToCompleteAll;
            echo "<p>You will finish all of your educations on or around " . date('r', $dateCompleted);          
        }
    }
    /* TOOL: Format Friend or Foe reports
     * Pulls recent reports from API, formats the results
     * for friend and enemy reveals with direct links to profiles
     * for the players listed.
     */
    else if($_POST['function'] == "friendOrFoeFormat")
    {      
        // pulls user reports
        $reportData = $apiConnection->apiCall($api,"user","reports");

        foreach($reportData['reports'] as $currentReport)
        {
            // Only output if report type is friend or foe
            if($currentReport['type'] == "friendorfoe")
            {
                $targetid = $currentReport['target'];

                // Link to directly message the target of the report and button to copy that target's report to clipboard
                echo "<h1>Target ID: <a href='https://www.torn.com/messages.php#/p=compose&XID=$targetid' target='_blank'>$targetid</a></h1>";  
                echo "<button onclick=\"copyToClipboard(document.getElementById('p$targetid').innerHTML)\">Copy to Clipboard</button>";
                
                // List each person who has added target as a friend with link to open profile in new tab      
                echo "<div id='p$targetid'><p><b>Added as a Friend: </b></p><p>";
                foreach($currentReport['report']['friendlist'] as $friends)
                {
                    $id = $friends['user_id'];
                    $name = $friends['name'];
                    echo "$name [<a href='https://www.torn.com/profiles.php?XID=$id' target='_blank'>$id</a>], ";
                }
                // List each person who has added target as an enemy with link to open profile in new tab
                echo ".</p><b>Added as an Enemy:</b><p>";
                foreach($currentReport['report']['enemylist'] as $enemies)
                {
                    $id = $enemies['user_id'];
                    $name = $enemies['name'];
                    echo "$name [<a href='https://www.torn.com/profiles.php?XID=$id' target='_blank'>$id</a>], ";
                }
                echo "</p></div>";
              }
          }    
          // include copy to clipboard script
          echo "<script src=\"js/torn.js\"></script>";
      }
    /* TOOL: Formats most wanted list with hyoerlinks to 
     * player profiles.
     */
    else if($_POST['function'] == "mostWantedFormat")
    {
        // API call for reports
        $reportData = $apiConnection->apiCall($api,"user", "reports");
        
        foreach($reportData['reports'] as $currentReport)
        {
            // Only output if report type is Most Wanted
            if($currentReport['type'] == "mostwanted")
            {
                // Find time the report was ran and format date for output
                $timestamp = $currentReport['timestamp'];                
                echo "<p><table><tr><td><h3>Date: " . date('r', $timestamp) . "</h3></td></tr><tr><th><h4>Highest Bounties</h4></th></tr>";

                // Outputs top 10 bounties with links to profiles
                foreach($currentReport['report']['toplist'] as $warrants)
                {
                    $id = $warrants['user_id'];
                    $name = $warrants['name'];        
                    $amount = $warrants['warrant'];
                    echo "<tr><td>$name [<a href='https://www.torn.com/profiles.php?XID=$id' target='_blank'>$id</a>]</td><td>$" . number_format($amount, 2) . "</td></tr>";
                }

                echo "<tr><th><h4>Random Bounties</h4></th></tr>";

                // Outputs the random bounties listed with links to profiles
                foreach($currentReport['report']['otherlist'] as $warrants)
                {
                    $id = $warrants['user_id'];
                    $name = $warrants['name'];        
                    $amount = $warrants['warrant'];

                    echo "<tr><td>$name [<a href='https://www.torn.com/profiles.php?XID=$id' target='_blank'>$id</a>]</td><td>$" . number_format($amount, 2) . "</td></tr>";
                }
                echo "</table></p>";
            }               
        }            
    }
    
    /* TOOL: Displays list of items in bazaar along with
     * both market and listed price to be viewed while flying
     * Viewable while normally unavailable in-game
     */
    else if($_POST['function'] == "bazaar")
    {
        // API call to get items in bazaar
        $itemData = $apiConnection->apiCall($api,"user", "bazaar");
        // Set currency to USD
        setlocale(LC_MONETARY, 'en_US');
        
        // create sortable table
        echo "<table border='1' class='table table-sortable'><thead><tr><td> </td><th>Item Name</th><th>Quantity</th><th>Listed Price</th><th>Market Price</th></tr></thead><tbody>";

        // Format and display each item in a row
        foreach($itemData['bazaar'] as $item)
        {
            $itemID = $item['ID'];
            $itemName = $item['name'];
            $itemType = $item['type'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            $marketPrice = $item['market_price'];
            echo "<tr><td><img src='https://www.torn.com/images/items/$itemID/medium.png'></td><td>$itemName</td><td align='center'>$quantity</td><td align='right'>$" . number_format($price) . "</td><td align='right'>$" . number_format($marketPrice) . "</td></tr>";
        }        
        // script included for sortable table
        echo "</tbody></table><script src='js/torn.js'></script>";
    }    
    /* TOOL: Displays inventory, viewable while flying, sortable, and
     * able to filter by type
     */
    else if($_POST['function'] == "inventory")
    {
        // API call to pull inventory
        $itemData = $apiConnection->apiCall($api,"user", "inventory");
        // Set currency to USD
        setlocale(LC_MONETARY, 'en_US');
        
        // Links to filter items by type
        echo "<p><a href='#' onclick='showHide(\"All\")'>All</a> "
            . "<a href='#' onclick='showHide(\"Primary\")'>Primary</a> "
            . "<a href='#' onclick='showHide(\"Secondary\")'>Secondary</a> "
            . "<a href='#' onclick='showHide(\"Melee\")'>Melee</a> "
            . "<a href='#' onclick='showHide(\"Temporary\")'>Temporary</a> " 
            . "<a href='#' onclick='showHide(\"Defensive\")'>Armor</a> "
            . "<a href='#' onclick='showHide(\"Clothing\")'>Clothing</a> "
            . "<a href='#' onclick='showHide(\"Medical\")'>Medical</a> "
            . "<a href='#' onclick='showHide(\"Drug\")'>Drugs</a> "
            . "<a href='#' onclick='showHide(\"EnergyDrink\")'>Energy Drinks</a> "
            . "<a href='#' onclick='showHide(\"Alcohol\")'>Alcohol</a> "
            . "<a href='#' onclick='showHide(\"Candy\")'>Candy</a> "
            . "<a href='#' onclick='showHide(\"Booster\")'>Boosters</a> "
            . "<a href='#' onclick='showHide(\"Enhancer\")'>Enhancers</a> "
            . "<a href='#' onclick='showHide(\"SupplyPack\")'>Supply Packs</a> "
            . "<a href='#' onclick='showHide(\"Electronic\")'>Electronics</a> "
            . "<a href='#' onclick='showHide(\"Jewelry\")'>Jewelry</a> "
            . "<a href='#' onclick='showHide(\"Flower\")'>Flowers</a> "
            . "<a href='#' onclick='showHide(\"Plushie\")'>Plushies</a> "
            . "<a href='#' onclick='showHide(\"Car\")'>Cars</a> "
            . "<a href='#' onclick='showHide(\"Virus\")'>Viruses</a> "
            . "<a href='#' onclick='showHide(\"Artifact\")'>Artifacts</a> "
            . "<a href='#' onclick='showHide(\"Book\")'>Books</a> "
            . "<a href='#' onclick='showHide(\"Special\")'>Special</a> "
            . "<a href='#' onclick='showHide(\"Other\")'>Miscellaneous</a> "
            . "<a href='#' onclick='showHide(\"Collectible\")'>Collectibles</a> ";
        
        echo "<table border='1' class='table table-sortable' id='table'><thead><tr><td> </td><th>Item Name</th><th>Quantity</th><th>Type</th><th>Market Price</th></tr></thead><tbody>";

        // Generate table rows for items in inventory
        foreach($itemData['inventory'] as $item)
        {
            $itemID = $item['ID'];
            $itemName = $item['name'];
            $itemTypeDisplay = $item['type'];
            $itemTypeClass = str_replace(' ', '', $itemTypeDisplay);
            $quantity = $item['quantity'];
            $marketPrice = $item['market_price'];            
            echo "<tr class='tr $itemTypeClass'><td><img src='https://www.torn.com/images/items/$itemID/medium.png'></td><td>$itemName</td><td align='center'>$quantity</td><td>$itemTypeDisplay</td><td align='right'>$" . number_format($marketPrice) . "</td></tr>";
        }
        // Script included for sortable table
        echo "</tbody></table><script src='js/torn.js'></script>";
    }
    /* TOOL: View list of faction members. Can either leave the search
     * field blank to view your own faction, or search for a faction using 
     * pre-seeded list in database.
     */
    else if($_POST['function'] == "faction")
    {
        // include and initialization of Database class for searchbox and updating names/adding new factions
        require_once('../userdatabase.php');
        $dbconnection = new Database();
        

        // If faction search is blank, pull user's faction, else pull faction ID from database
        if($_POST['factionSearch'] == "")
        {
            
            // Pulls user data for faction ID
            $userData = $apiConnection->apiCall($api,"user");
            $factionID = $userData['faction']['faction_id'];
            
            // Checks to see whether user has faction API access
            $factionOCData = $apiConnection->apiCallFaction($api, "faction", "crimes");

            
            if($factionOCData == false)
            {
                // User does not have faction API access, OC info will be omitted
                $factionAPIAccess = false;
            }
            else // User has faction API access
            {
                // User has faction API access, pull current timestamp and set variable
                // for adding OC columns
                $factionAPIAccess = true;
                $currentTime = time();
                // Set timezone to TCT
                date_default_timezone_set("GMT");

                // Go through OC Data
                foreach($factionOCData['crimes'] as $factionOCID => $factionOC)
                {
                    // Timestamp for when OC is ready
                    $finishTime = $factionOC['time_ready'];

                    
                    foreach($factionOC['participants'] as $factionMemberKey)
                    {
                        foreach($factionMemberKey as $factionMemberID => $factionMemberInfo)
                        {   
                            // If member's status is null, it means OC was canceled, skip
                            // rest of loop and go to the next OC
                            if($factionMemberInfo == "")
                            {
                                break;
                            }

                            // if OC is ready, check member status and format table data
                            if($currentTime > $finishTime)                        
                            {
                                if($factionMemberInfo['state'] != "Okay")
                                {
                                    $details = $factionMemberInfo['details'];
                                    $ocColumn[$factionMemberID] = "<td bgcolor='Orange'>Delaying OC due to $details</td>";
                                }
                                else
                                {
                                    $ocColumn[$factionMemberID] = "<td bgcolor='Green'>OC Ready</td>";
                                }
                            }
                            else // If OC is not ready, display date/time when ready in TCT
                            {
                                $ocColumn[$factionMemberID] = "<td>" . date("r", $finishTime) . "</td>";
                            }
                        }
                    }

                }      
            }                
        }
        else // User has typed a name into faction search
        {

            // Sanitize search box input
            $factionSearch = mysqli_real_escape_string($dbconnection->OpenConnection(), $_POST['factionSearch']);

            // Query DB for matching faction
            $sql = "select * from tornfactions WHERE factionName = '$factionSearch' OR factionID = '$factionSearch';";
            $results = $dbconnection->query($sql);

            // check for results and assign to variables if successful
            if(mysqli_num_rows($results) == 1)
            {
                $faction = mysqli_fetch_assoc($results);
                $factionID = $faction['factionID'];
                $factionNameDB = $faction['factionName'];
                $factionInDB = true;
            }
            // If no results, save ID if it's a number to search API for new factions
            else if(mysqli_num_rows($results) == 0 && is_int($factionNameDB))
            {
                $factionID = $factionSearch;
                $factionInDB = false;
            }
        }

        // Pull faction API data and assign name to variable
        $factionData = $apiConnection->apiCall($api,"faction", "", $factionID);
        $factionNameAPI = $factionData['name'];

        // Add new faction ID is valid, but does not exist in DB 
        if(!$factionInDB)
        {
            $sql = "INSERT into tornfactions(factionID,factionName) VALUES ($factionID,'$factionNameAPI';";
        }
        // Update name if the faction name in DB is different from the API
        else if($factionNameAPI != $factionNameDB)
        {
            $sql = "UPDATE tornfactions SET factionName = '$factionNameAPI' WHERE factionID = $factionID;";
            $dbconnection->query($sql);
        }

        // create array of faction members
        $factionMembers = $factionData['members'];

        echo "<h1><a href='https://www.torn.com/factions.php?step=profile&ID=$factionID' target='_blank'>$factionNameAPI</a></h1>";

        // Create sortable table
        echo "<table border='1' class='table table-sortable factionTable'>"
        . "<thead><tr><th>Online</th><th>Name</th><th>Level</th><th>Position</th><th>Status</th>";

        // If user has faction API access, create column for OC
        if($factionAPIAccess)
        {
            echo "<th>Organized Crimes</th>";
        }    

        echo "</tr></thead><tbody>";

        foreach($factionMembers as $memberID => $member)
        {
            // assign userID to variables
            $id = $memberID;
            $online = $member['last_action']['status'];
            $name = $member['name'];
            $level = $member['level'];
            $status = $member['status']['description'];
            $position = $member['position'];
            $state = $member['status']['state'];

            // Check state of member and color row accordingly
            switch($state)
            {
                case "Hospital":
                    $row = " bgcolor='lightcoral'";
                    break;
                case "Jail":
                    $row = " bgcolor='lightblue'";
                    break;
                case "Traveling":
                    $row = " bgcolor='aqua'";
                    break;   
                case "Abroad";
                    $row = " bgcolor='aqua'";
                    break;
                case "Fallen"; // Hide RIP Members
                    $row = " style='display:none;'";
                    break;
                default:
                    $row = "";
            }

            // Color first column according to online status
            switch($online)
            {
                case "Online":
                    $onlineColor = " bgcolor='green'";
                    break;
                case "Idle":
                    $onlineColor = " bgcolor='yellow'";
                    break;
                case "Offline":
                    $onlineColor = " bgcolor='red'";
                    break;
            }
            
            echo "<tr$row><td$onlineColor>$online</td><td><a href='https://www.torn.com/profiles.php?XID=$id' target='_blank'>$name</a></td>"
                    . "<td>$level</td><td>$position</td><td>$status</td>";

            // Add info for OC if user has access
            if($factionAPIAccess)
            {
                if($ocColumn[$id])
                {
                    echo $ocColumn[$id];
                }
                else
                {
                    echo "<td>Not in OC</td>";
                }
            }                    
            echo "</tr>";
        }
        // include script for sorting table
        echo "</tbody></table><script src='js/torn.js'></script>";
    }
