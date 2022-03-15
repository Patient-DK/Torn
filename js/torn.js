/**
 * Sorts a HTML table.
 * 
 * @param {HTMLTableElement} table The table to sort
 * @param {number} column The index of the column to sort
 * @param {boolean} asc Determines if the sorting will be in ascending
 */
function sortTableByColumn(table, column, asc = true) 
{
    const dirModifier = asc ? 1 : -1;
    const tBody = table.tBodies[0];
    const rows = Array.from(tBody.querySelectorAll("tr"));
    
    const removeCurrency = "/\$|,/g";

    // Sort each row
    const sortedRows = rows.sort((a, b) => 
    {
        const aColText = isNaN(a.querySelector(`td:nth-child(${ column + 1 })`).textContent.replace(/\$|,/g, '')) ? a.querySelector(`td:nth-child(${ column + 1 })`).textContent : Number(a.querySelector(`td:nth-child(${ column + 1 })`).textContent.replace(/\$|,/g, ''));
        const bColText = isNaN(b.querySelector(`td:nth-child(${ column + 1 })`).textContent.replace(/\$|,/g, '')) ? b.querySelector(`td:nth-child(${ column + 1 })`).textContent : Number(b.querySelector(`td:nth-child(${ column + 1 })`).textContent.replace(/\$|,/g, ''));

        return aColText > bColText ? (1 * dirModifier) : (-1 * dirModifier);
    });

    // Remove all existing rows from the table
    while (tBody.firstChild) {
        tBody.removeChild(tBody.firstChild);
    }

    // Re-add the sorted rows
    tBody.append(...sortedRows);

    // alternate ascending/descending sorting on click
    table.querySelectorAll("th").forEach(th => th.classList.remove("th-sort-asc", "th-sort-desc"));
    table.querySelector(`th:nth-child(${ column + 1})`).classList.toggle("th-sort-asc", asc);
    table.querySelector(`th:nth-child(${ column + 1})`).classList.toggle("th-sort-desc", !asc);
}

// Make headers clickable    
document.querySelectorAll(".table-sortable th").forEach(headerCell => {
    headerCell.addEventListener("click", () => {
        const tableElement = headerCell.parentElement.parentElement.parentElement;
        const headerIndex = Array.prototype.indexOf.call(headerCell.parentElement.children, headerCell);
        const Ascending = headerCell.classList.contains("th-sort-asc");

        sortTableByColumn(tableElement, headerIndex, !Ascending);
    });
});

/**
 * Shows or hides rows based on selection
 * 
 * @param {type} type Selection to be displayed
 */
function showHide(type)
{
    const itemType = type;  
    
    var elements = document.getElementsByClassName("tr");
    
    if(itemType !== "All")
    {     
        for(var i=0; i < elements.length; i++)
        {
            elements[i].style.display = "none";
        }
        
        var elements = document.getElementsByClassName(itemType);   
        for(var i=0; i < elements.length; i++)
        {
            elements[i].style.display = "table-row";
        }
    }
    else
    {
        for(var i=0; i < elements.length; i++)
        {

            elements[i].style.display = "table-row";
        }
    }   
}

function autoComplete()
{
    $("#factionSearch").autocomplete(
            {
                source: '/torn/factionlist.php'
            });
}

function copyToClipboard(str) {
  function listener(e) {
    e.clipboardData.setData("text/html", str);
    e.clipboardData.setData("text/plain", str);
    e.preventDefault();
  }
  document.addEventListener("copy", listener);
  document.execCommand("copy");
  document.removeEventListener("copy", listener);
};
