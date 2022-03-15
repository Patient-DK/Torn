<!DOCTYPE html>

<html>
    <head>
        <title>Just Some Torn Stuff</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="torn.css">
        <link rel="stylesheet" href="js/jquery-ui/jquery-ui.css">
        <script type="text/javascript" src="js/jquery.js"></script>
        <script type="text/javascript" src="js/jquery-ui/jquery-ui.js"></script>
        
    </head>
    <body>
        <form action="torn.php" method="post" autocomplete="off">
            <?php echo "<p>API Key: <input type='text' name='key' value='". $_COOKIE['key'] . "'>";
            
                if(isset($_COOKIE['key']) || $_POST['saveKey'])
                {
                    $checkSaveAPI = "checked";
                }
                else
                {
                    $checkSaveAPI = "";
                }

                echo "<input type='checkbox' name='saveKey' $checkSaveAPI><label>Save API Key(Uses cookies)</label>";
            ?>
            
            </p>
            <p><select name="function" onchange="
                if(this.value==='faction')
                {
                    this.form['factionSearch'].style.visibility='visible';
                    document.getElementById('factionSearchLabel').style.visibility='visible';
                }
                else 
                {
                    this.form['factionSearch'].style.visibility='hidden';
                    document.getElementById('factionSearchLabel').style.visibility='hidden';
                };">
                    <option value="faction">View Faction Members(TESTING)</option>
                    <option value="nextStock">Get List of Stocks to Buy Sorted by ROI</option>
                    <option value="allStocks">Get List of All Stocks Sorted by ROI</option>
                    <option value="inventory">View Inventory</option>
                    <option value="bazaar">View Bazaar</option>
                    <option value="friendOrFoeFormat">Format Friend or Foe Report</option>
                    <option value="mostWantedFormat">Format Most Wanted List</option>
                    <option value="educationLeft">Total Education Time Left</option>
                </select></p>
                
                <script>
                    $( function() 
                    {
                        $( "#factionSearch" ).autocomplete({
                          source: '/torn/factionlist.php'
                        });
                    });
                </script>
                
                <div><p id="factionSearchSection"><label for="factionSearch" id="factionSearchLabel">Faction Name or ID(Leave blank for your own faction): </label><input type="text" name="factionSearch" id="factionSearch" value=""></p></div>
                
                <p><input type="submit" value="Submit"></p>
        </form>
        