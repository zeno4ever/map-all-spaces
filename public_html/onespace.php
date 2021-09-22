<!DOCTYPE html>
<? require '../private/init.php';?>
<html lang="en">
<head>
    <title>One Space Status</title>

    <meta charset="UTF-8">
    <meta name="Map hackerspaces/fablabs/makerspaces "
        content="Dynamic map with all hackerspace, fablabs and makerspaces">
    <link rel="stylesheet" type="text/css" href="/css/style.css">
    <!-- If IE use the latest rendering engine -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Set the page to the width of the device and set the zoon level -->
    <meta name="viewport" content="width = device-width, initial-scale = 1">

    <!-- jquery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-2M9QVB70G3"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-2M9QVB70G3');
    </script>
</head>
<body>
    <div id="header">
        <? include $PRIVATE.'/layout/navigate.php' ?>
    </div>
    <div>
        <img id="spaceimage" class="spaceimage" src="/image/hslogo.png">
        <p id="textstatus" class="status">Select a space!</p>
        <div id="textohter" class="other"></div>
    </div>
    <form >
        <label>Choose a space:</label>
        <select id="spaces" onchange="setCookie()"></select>
    </form>
    <div id="help">
        This page is meant to be used as static page to view current the open/closed status of your selected page. It will be
        refreshed every 10 minutes. Once a space is selected the header and this text wil be disappear.
    </div>
    <script>
        const getCookie = (name) => {
                const cookies = document.cookie.split(';');
                for (let i = 0; i < cookies.length; i++) {
                    let c = cookies[i].trim().split('=');
                    if (c[0] === name) {
                        return c[1];
                    }
                }
                return "";
        };

        function forceUrlProtocol(url) {
            const urlObj = new URL(url);
            urlObj.protocol = location.protocol;            
            return urlObj.toString();
        }

        var spaceurl = '';
        var allspaces = {};
        var spacename = '';

        //read url
        var queryString = location.search;
        let params = new URLSearchParams(queryString);

        // let urlspacename = params.get("space");
        // if (urlspacename != null) {
        //     spacename = urlspacename;
        // };
        spacename = params.get("space");
        console.log('spacename : '+spacename);


        $.getJSON("https://raw.githubusercontent.com/SpaceApi/directory/master/directory.json", function (data) {
            $.each(data, function (key, val) {

                allspaces[key]=val;

                if(key == spacename) {
                    $('#spaces').append("<option selected='selected' value='" + key + "'>" + key + "</option>");
                    getSpaceAPIStatus(val);
                } else {
                    $('#spaces').append("<option value='" + key + "'>" + key + "</option>");                    
                };

            });
        });

        function setCookie(params) {
            spacename = document.getElementById('spaces').value;

            getSpaceAPIStatus(allspaces[spacename]);

            $("#header").hide();
            $("#help").hide();

            location.href = '/onespace.html?space='+ encodeURIComponent(spacename);
        };

        //setInterval(timerSpaceAPI, 10 * 60 * 1000); //every 5 minutes
        setInterval(timerSpaceAPI, 10*1000); //every 5 minutes
       
        function timerSpaceAPI() {
            console.log('timer all ' + allspaces[spacename]);
            console.log('timer name '+ spacename);
            if (spacename != '') {
                getSpaceAPIStatus(allspaces[spacename]);
            };
        };

        function getSpaceAPIStatus(url) {
            //for testing 
            //url ='/testapi.php';

            $("#header").hide();
            $("#help").hide();

            $('#spaceimage').attr('src', '/image/hslogo.png');
            $('#textstatus').text('Please wait....');
            $('#textohter').text('');

            //$.getJSON(forceUrlProtocol(url), function (data) {
            $.getJSON(url, function(data)  {
                let datestring = '';
                let iconopen = '';
                let iconclosed = '';
                let ohterstring = '';
                let lastchange = 0;

                console.log('spaceapi data:');
                console.log(data);

                //open/closed 
                if (parseFloat(data.api) < 0.13) {
                    openstatus = data.open == true;
                    lastchange = data.lastchange;
                } else {
                    openstatus = data.state.open == true;
                    lastchange = data.state.lastchange;

                    if (data.state.icon != null) {
                        iconopen = String(data['state']['icon']['open']);
                        iconclosed = String(data['state']['icon']['closed']);
                    };

                }

                if (data.logo != null) {
                    $('#spaceimage').attr('src', forceUrlProtocol(data.logo));
                };

                if (openstatus == true) {
                    if (iconopen != "") {
                        $('#spaceimage').attr('src', forceUrlProtocol(iconopen));
                    };
                    datestring = 'Open';
                    $('#textstatus').removeClass("open closed").addClass("open");
                } else {
                    if (iconclosed != "") {
                        $('#spaceimage').attr('src', forceUrlProtocol(iconclosed));
                    };
                    datestring = 'Closed';
                    $('#textstatus').removeClass("open closed").addClass("closed");
                };

                if (lastchange != null) {
                    datestring += " since " + new Date(lastchange * 1000).toLocaleString();
                };

                if (data.sensors != null) {
                    if (data.sensors.people_now_present != null) {
                        ohterstring += "Present : ";
                        let connections = 0;
                        data.sensors.people_now_present.forEach(element => {
                            connections += element.value;
                            ohterstring += element.names;
                        });
                        if (connections !=0) {
                            ohterstring += " Connections : " + connections;
                        };
                    };
                };

                $('#textstatus').text(datestring);
                $('#textohter').text(ohterstring);
                $(document).attr('title', 'Status '+ spacename);

            })
            .fail(function (jqxhr, textStatus, error) {
                //console.log(jqxhr);
                //console.log(textStatus);
                //console.log(error);

                $('#textstatus').removeClass("open closed").addClass("closed");
                $('#textstatus').text('Get SpaceAPI failed, check if CORS on server is enabled or https access.');
            });
        };
    </script>
</body>
</html>