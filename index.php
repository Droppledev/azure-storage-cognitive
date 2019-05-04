<?php
/**----------------------------------------------------------------------------------
 * Microsoft Developer & Platform Evangelism
 *
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY KIND, 
 * EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE IMPLIED WARRANTIES 
 * OF MERCHANTABILITY AND/OR FITNESS FOR A PARTICULAR PURPOSE.
 *----------------------------------------------------------------------------------
 * The example companies, organizations, products, domain names,
 * e-mail addresses, logos, people, places, and events depicted
 * herein are fictitious.  No association with any real company,
 * organization, product, domain name, email address, logo, person,
 * places, or events is intended or should be inferred.
 *----------------------------------------------------------------------------------
 **/

/** -------------------------------------------------------------
# Azure Storage Blob Sample - Demonstrate how to use the Blob Storage service. 
# Blob storage stores unstructured data such as text, binary data, documents or media files. 
# Blobs can be accessed from anywhere in the world via HTTP or HTTPS. 
#
# Documentation References: 
#  - Associated Article - https://docs.microsoft.com/en-us/azure/storage/blobs/storage-quickstart-blobs-php 
#  - What is a Storage Account - http://azure.microsoft.com/en-us/documentation/articles/storage-whatis-account/ 
#  - Getting Started with Blobs - https://azure.microsoft.com/en-us/documentation/articles/storage-php-how-to-use-blobs/
#  - Blob Service Concepts - http://msdn.microsoft.com/en-us/library/dd179376.aspx 
#  - Blob Service REST API - http://msdn.microsoft.com/en-us/library/dd135733.aspx 
#  - Blob Service PHP API - https://github.com/Azure/azure-storage-php
#  - Storage Emulator - http://azure.microsoft.com/en-us/documentation/articles/storage-use-emulator/ 
#
 **/

require_once 'vendor/autoload.php';
require_once "./random_string.php";

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;
use MicrosoftAzure\Storage\Blob\Models\ListContainersOptions;

$connectionString = "DefaultEndpointsProtocol=https;AccountName=" . getenv('ACCOUNT_NAME') . ";AccountKey=" . getenv('ACCOUNT_KEY');
$subscriptionKey = getenv('SUBS_KEY');
// Create blob client.
$blobClient = BlobRestProxy::createBlobService($connectionString);

function createContainerIfNotExists($blobRestProxy, $containerName)
{
    // See if the container already exists.
    $listContainersOptions = new ListContainersOptions();
    $listContainersOptions->setPrefix($containerName);
    $listContainersResult = $blobRestProxy->listContainers($listContainersOptions);
    $containerExists = false;
    foreach ($listContainersResult->getContainers() as $container) {
        if ($container->getName() == $containerName) {
            // The container exists.
            $containerExists = true;
            // No need to keep checking.
            break;
        }
    }
    if (!$containerExists) {
        echo "Creating container.\n";
        $blobRestProxy->createContainer($containerName);
        echo "Container '" . $containerName . "' successfully created.\n";
    }
}

$fileToUpload = "HelloWorld.txt";

// Create container options object.
$createContainerOptions = new CreateContainerOptions();

// Set public access policy. Possible values are
// PublicAccessType::CONTAINER_AND_BLOBS and PublicAccessType::BLOBS_ONLY.
// CONTAINER_AND_BLOBS:
// Specifies full public read access for container and blob data.
// proxys can enumerate blobs within the container via anonymous
// request, but cannot enumerate containers within the storage account.
//
// BLOBS_ONLY:
// Specifies public read access for blobs. Blob data within this
// container can be read via anonymous request, but container data is not
// available. proxys cannot enumerate blobs within the container via
// anonymous request.
// If this value is not specified in the request, container data is
// private to the account owner.
$createContainerOptions->setPublicAccess(PublicAccessType::CONTAINER_AND_BLOBS);

// Set container metadata.
$createContainerOptions->addMetaData("key1", "value1");
$createContainerOptions->addMetaData("key2", "value2");

// $containerName = "blockblobs" . generateRandomString();
$containerName = "imagecontainer";

try {
    // Create container.
    // $blobClient->createContainer($containerName, $createContainerOptions);
    createContainerIfNotExists($blobClient, $containerName);

    if (isset($_FILES['imageupload'])) {
        $tmp_file = $_FILES['imageupload']['tmp_name'];
        $file_name = $_FILES['imageupload']['name'];
        $uploaded = move_uploaded_file($tmp_file, $file_name);
        if ($uploaded) {
            $fileToUpload = $file_name;
            // Getting local file so that we can upload it to Azure
            $myfile = fopen($fileToUpload, "r") or die("Unable to open file!");
            fclose($myfile);

            # Upload file as a block blob
            echo "Uploaded BlockBlob: " . PHP_EOL;
            echo $fileToUpload;
            echo "<br />";

            $content = fopen($fileToUpload, "r");

            //Upload blob
            $blobClient->createBlockBlob($containerName, $fileToUpload, $content);
            unlink($fileToUpload);
        }
    }
    // List blobs.
    $listBlobsOptions = new ListBlobsOptions();
    // $listBlobsOptions->setPrefix("HelloWorld");

    echo "<h1>Blobs in Container</h1>";
    ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th scope="col">Name</th>
                <th scope="col">URL</th>
                <th scope="col">Thumbnail</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $blobList = $blobClient->listBlobs($containerName);
            foreach ($blobList->getBlobs() as $blob) :
                $blobData = $blobClient->getBlob($containerName, $blob->getName());
                $imgData = base64_encode(stream_get_contents($blobData->getContentStream()));
                ?>
                <tr>
                    <td><?= $blob->getName() ?></td>
                    <td><?= $blob->getUrl() ?></td>
                    <td><img width="100" height="100" src="data:image/jpeg;base64,<?= $imgData ?>" /></td>
                    <td><button class="btn btn-primary" onclick="processImage('data:image/jpeg;base64,<?= $imgData ?>')">Analyze image</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php
} catch (ServiceException $e) {
    // Handle exception based on error codes and messages.
    // Error codes and messages are here:
    // http://msdn.microsoft.com/library/azure/dd179439.aspx
    $code = $e->getCode();
    $error_message = $e->getMessage();
    echo $code . ": " . $error_message . "<br />";
} catch (InvalidArgumentTypeException $e) {
    // Handle exception based on error codes and messages.
    // Error codes and messages are here:
    // http://msdn.microsoft.com/library/azure/dd179439.aspx
    $code = $e->getCode();
    $error_message = $e->getMessage();
    echo $code . ": " . $error_message . "<br />";
}

?>
<!DOCTYPE html>
<html>

<head>
    <title>Azure Storage and Cognitive</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js" integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

</head>

<body class="container">

    <script type="text/javascript">
        var subskey = '<?= $subscriptionKey ?>';
        makeblob = function(dataURL) {
            var BASE64_MARKER = ';base64,';
            if (dataURL.indexOf(BASE64_MARKER) == -1) {
                var parts = dataURL.split(',');
                var contentType = parts[0].split(':')[1];
                var raw = decodeURIComponent(parts[1]);
                return new Blob([raw], {
                    type: contentType
                });
            }
            var parts = dataURL.split(BASE64_MARKER);
            var contentType = parts[0].split(':')[1];
            var raw = window.atob(parts[1]);
            var rawLength = raw.length;

            var uInt8Array = new Uint8Array(rawLength);

            for (var i = 0; i < rawLength; ++i) {
                uInt8Array[i] = raw.charCodeAt(i);
            }

            return new Blob([uInt8Array], {
                type: contentType
            });
        }

        function processImage(blob) {
            // **********************************************
            // *** Update or verify the following values. ***
            // **********************************************

            // Replace <Subscription Key> with your valid subscription key.
            var subscriptionKey = subskey;

            // You must use the same Azure region in your REST API method as you used to
            // get your subscription keys. For example, if you got your subscription keys
            // from the West US region, replace "westcentralus" in the URL
            // below with "westus".
            //
            // Free trial subscription keys are generated in the "westus" region.
            // If you use a free trial subscription key, you shouldn't need to change
            // this region.
            var uriBase =
                "https://southeastasia.api.cognitive.microsoft.com/vision/v2.0/analyze";

            // Request parameters.
            var params = {
                "visualFeatures": "Categories,Description,Color",
                "details": "",
                "language": "en",
            };

            // Display the image.
            var sourceImageUrl = blob
            document.querySelector("#sourceImage").src = sourceImageUrl;

            // Make the REST API call.
            $.ajax({
                    url: uriBase + "?" + $.param(params),
                    processData: false,

                    // Request headers.
                    beforeSend: function(xhrObj) {
                        xhrObj.setRequestHeader("Content-Type", "application/octet-stream");
                        xhrObj.setRequestHeader(
                            "Ocp-Apim-Subscription-Key", subscriptionKey);
                    },

                    type: "POST",

                    // Request body.
                    data: makeblob(blob),
                })

                .done(function(data) {
                    // Show formatted JSON on webpage.
                    $("#responseTextArea").val(JSON.stringify(data, null, 2));
                })

                .fail(function(jqXHR, textStatus, errorThrown) {
                    // Display error message.
                    var errorString = (errorThrown === "") ? "Error. " :
                        errorThrown + " (" + jqXHR.status + "): ";
                    errorString += (jqXHR.responseText === "") ? "" :
                        jQuery.parseJSON(jqXHR.responseText).message;
                    alert(errorString);
                });
        };
    </script>

    <h1>Upload image:</h1>

    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="imageupload" id="imageupload">
        <input class="btn btn-primary" type="submit" value="Upload">
    </form>

    <br><br>
    <div id="wrapper" style="width:1020px; display:table;">
        <div id="jsonOutput" style="width:600px; display:table-cell;">
            Response:
            <br><br>
            <textarea id="responseTextArea" class="UIInput" style="width:580px; height:400px;"></textarea>
        </div>
        <div id="imageDiv" style="width:420px; display:table-cell;">
            Source image:
            <br><br>
            <img id="sourceImage" width="400" />
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
</body>

</html>