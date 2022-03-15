<?php

class TornAPI
{
    // Accept string for selections and API as arguments
    function apiCall($api, $category,$selections="", $id="")
    {
        $tornCall = "https://api.torn.com/$category/$id?selections=$selections&key=$api";        
        $tornData = json_decode(file_get_contents($tornCall), true);
        
        if($tornData['error']['code'] >= 1 && $tornData['error']['code'] <= 16)
        {
            echo "Error, please double check your API Key";
            die();
        }
        else
        {
            return $tornData;
        }
    }
    // Alternative to apiCall that allows for false instead of die() on identity relationship error
    // For purposes of displaying information based on faction API access
    function apiCallFaction($api,$category,$selections="",$id="")
    {            
        $tornCall = "https://api.torn.com/$category/$id?selections=$selections&key=$api";        
        $tornData = json_decode(file_get_contents($tornCall), true);
        
        if($tornData['error']['code'] == 16)
        {
            return false;
        }
        else if($tornData['error']['code'] >= 1 && $tornData['error']['code'] <= 15)
        {
            echo "Error, please double check your API Key";
            die();
        }
        else
        {
            return $tornData;
        }
    }
}
