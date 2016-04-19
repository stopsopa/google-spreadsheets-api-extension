[![Build Status](https://travis-ci.org/stopsopa/google-spreadsheets-api-extension.svg?branch=master)](https://travis-ci.org/stopsopa/google-spreadsheets-api-extension)
[![Latest Stable Version](https://poser.pugx.org/stopsopa/google-spreadsheets-api-extension/v/stable)](https://packagist.org/packages/stopsopa/google-spreadsheets-api-extension)
[![License](https://poser.pugx.org/stopsopa/google-spreadsheets-api-extension/license)](https://packagist.org/packages/stopsopa/google-spreadsheets-api-extension)


# stopsopa/google-spreadsheets-api-extension

## Instalation

Follow packagist instructions: [Packagist](https://packagist.org/packages/stopsopa/google-spreadsheets-api-extension
)

## Setup access to google spreadsheet documents

First You need to generate credentials in [Google API Console](https://console.developers.google.com) type **Service account keys** in [google console](https://console.developers.google.com) (read [more](https://support.google.com/cloud/answer/6158849?hl=en#serviceaccounts)). After that you recieve file '.p12' which is a private key and special e-mail address (eq: applicationname@applicationname.iam.gserviceaccount.com) that you should use to give access to api to specific spreadsheets in google-drive.

## Setup this library

```
use Stopsopa\GoogleSpreadsheets\Services\GoogleSpreadsheets;

$service = new GoogleSpreadsheets();

$service->setupByServiceAccountKey(
    'pathtofile.p12', 
    'applicationname@applicationname.iam.gserviceaccount.com'
);
```

... and now you are ready to go.

# Api 

Most of methods is pretty self-explanatory so normally you can explore them through IDE or just by exploring returned data structures (especially if you read [terminology](https://developers.google.com/google-apps/spreadsheets/#terminology_used_in_this_guide) used in Sheets API), they don't need to be explained here in details, but it is also good thing to list them here. It is also good idea to see [tests](https://github.com/stopsopa/google-spreadsheets-api-extension/blob/master/Tests/GoogleSpreadsheetsTest.php) starting from method **testFindWorksheets**.


## Methods of GoogleSpreadsheets class

Method with default parameters | Returned type | Additional description
------------------------------ | ------------- | ----------------------
$service->findSpreadsheets($rawResponse = false); | array | 
$service->findWorksheets($key, $rawResponse = false); | array | 
$service->getWorksheetMetadata($key, $wid); | array |  
$service->updateWorksheetMetadata($key, $wid, $title = null, $rows = null, $cols = null); | array | 
$service->findWorksheetData($key, $wid, $rawResponse = false, $filter = array()); | array | 
$service->deleteWorksheet($key, $wid); | array | 
$service->update($key, $wid, $data); | array | [doc](https://github.com/stopsopa/google-spreadsheets-api-extension#method-googlespreadsheets-update)
$service->findFirstFreeRowForData($key, $wid); | int (1 indexed) | 
$service->getList($key, $wid); |  [GoogleSpreadsheetsList](https://github.com/stopsopa/google-spreadsheets-api-extension/blob/master/Services/GoogleSpreadsheetsList.php) | 

## Methods of GoogleSpreadsheetsList class

Read more about [list based feed](https://developers.google.com/google-apps/spreadsheets/data#work_with_list-based_feeds).

Method with default parameters | Returned type | Additional description
------------------------------ | ------------- | -----------------------
$list->add($data); | array | [doc](https://github.com/stopsopa/google-spreadsheets-api-extension/blob/master/README.md#method-googlespreadsheetslist-add)
$list->update($row, $data); | array | $row is 1 indexed, $data format like above
$list->get($filters = null); | array | $filters format see [this](https://github.com/stopsopa/google-spreadsheets-api-extension#filter). <br />Usually use only min-row, max-row.
$list->; |
$list->; |


## Commonly used parameters

### $rawResponse:

Many of this methods has special parameter **$rawResponse**, setting up this to true turns returned data structure to raw form, just like it's comes from google api. But in most cases it is better to leave this parameter as is and get more consist and better to iterate through data structure at the output of this methods. 

## $key

You can find the key in the spreadsheet URL (bolded in below example).

https<b></b>://docs.google.com/spreadsheets/d/**1IAP4HOacD4Az6q_PFfxxxxxxxxxxxxxxxx9KBX-IMO25s**/edit?usp=sharing

## $wid

This is unique identifier for each worksheet in spreadsheet, You can get these ids by using method **findWorksheets**.

## $filter 

This parameter is used to fetch specific rows or columns ranges from worksheet, eq:

    
      $result = $service->findWorksheetData($key, $wid, false, array(
        'max-col' => 4,
        'max-row' => 3
      ));
      
For more informations see in google api [Fetch specific rows or columns](https://developers.google.com/google-apps/spreadsheets/data#fetch_specific_rows_or_columns).

## Other details

### Method GoogleSpreadsheets->update()

        $service->update($key, $wid, array(
            'A1' => "Name",
            'B1' => "Surname",
            'C1' => "Age",
            'D1' => "Weight",
            'E1' => "Height",
            'A2' => "Something else... ",
            'R2C5' => "And again...",
            'R3C2' => '' // empty string to delete value from cell
        ));
        
Using this method you can use two types of [positioning notations](https://developers.google.com/google-apps/spreadsheets/data#work_with_cell-based_feeds). Using method *update* you can set/update/delete data in many cells by one [batch request](https://developers.google.com/google-apps/spreadsheets/data#update_multiple_cells_with_a_batch_request).       

### Method GoogleSpreadsheetsList->add()

        $list->add(array(
            'Name' => 'John',
            'Surname' => 'Smith',
            'Age' => '35'
        ));
        
To read more about basic assumptions that describes working with list based feeds go [here](https://developers.google.com/google-apps/spreadsheets/data#work_with_list-based_feeds).

    
    














