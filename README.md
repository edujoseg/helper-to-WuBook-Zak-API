# helper-to-WuBook-Zak-API
Small scripts as helper to complex requests to WuBook Zak API

## Requirements

- PHP > 5.x
- XML support for PHP
- JSON support for PHP
- XMLRPC for PHP (http://gggeek.github.io/phpxmlrpc/)

## Setting configuration

Complete values into `config.php` script.

## How to use `getInfobR.php`
This script get invoices of reservations between dates fd (check-in date) and td (check-out date), using format DD-MM-YYYY

Perform a request from client side as:
```
$.post("getInfobR.php", { toDate: td, fromDate: fd }, function(result){
			html_out += "<table id='datatable'><tr><th>rcode</th><th>sn</th><th>vat_8</th><th>vat_16</th>	<th>camount</th><th>dpayamount</th><th>gen_doc_type</th><th>get_doc_number</th><th>birthdate</th><th>first_name</th><th>last_name</th><th>cash</th><th>cc</th><th>other</th><th>adults</th><th>th</th><th>dfrom</th><th>dto</th></tr>";
			$.each(result, function(index, obj) {
				html_out += "<tr>";
				$.each(obj, function(key, value) {
					html_out += "<td>" + value + "</td>";
				});
				html_out += "</tr>";
      });
      html_out += "</table>";
			$("#demo").html(html_out);
}, "json");
```
## Example
`audit.html` is an example using jQuery libraries, to get CSV or Excel spreadsheet.
Observe you must install:
- excellentexport.min.js (https://github.com/jmaister/excellentexport), 
- moment-with-locales.min.js (http://momentjs.com/)
- jquery-ui.min.js (https://jqueryui.com/)
