<!DOCTYPE html>
<?php require '../private/init.php'; ?>
<html lang="en">

<head>
    <title>Map all spaces</title>

    <meta charset="UTF-8">
    <meta name="Map hackerspaces/fablabs/makerspaces " content="Dynamic map with all hackerspace, fablabs and makerspaces">
    <link rel="stylesheet" type="text/css" href="/css/style.css">
    <link rel="apple-touch-icon" href="/image/hslogo.png">
    <!-- If IE use the latest rendering engine -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Set the page to the width of the device and set the zoon level -->
    <meta name="viewport" content="width = device-width, initial-scale = 1">

    <!-- jquery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <!-- Leaflet v1.0.1 -->
    <link rel="stylesheet" href="//unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="//unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <!--script src="leaflet.featuregroup.subgroup.js"></script-->
    <script src="https://unpkg.com/leaflet.featuregroup.subgroup@1.0.2/dist/leaflet.featuregroup.subgroup.js"></script>

    <!-- Leaflet loading spinner-->
    <script src="/dist/spin.min.js" charset="utf-8"></script>
    <script src="/dist/leaflet.spin.min.js" charset="utf-8"></script>

    <!-- Leaflet clusters / groeps -->
    <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster/dist/"></script>

    <link rel="stylesheet" type="text/css" href="/dist/MarkerCluster.Default.css">
    <link rel="stylesheet" type="text/css" href="/dist/MarkerCluster.css">

    <!-- Leaflet search -->
    <link rel="stylesheet" href="/css/leaflet-search.css" />
    <script src="/dist/leaflet-search.js"></script>

    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-2M9QVB70G3"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-2M9QVB70G3');
    </script>

</head>

<body>
    <div id="header">
        <?php include $PRIVATE . '/layout/navigate.php' ?>
    </div>
    <div class="container">
        <div id="map"></div>
    </div>
    <script>
        //const queryString = window.location.search;
        const urlParams = new URLSearchParams(window.location.search);

        if (navigator.geolocation && !urlParams.has('lat')) {
            //if location is allowed
            navigator.geolocation.getCurrentPosition(getPos);
        };

        var map = L.map('map').setView([52, 12], 4); //default zoom on europe

        map.spin(true);

        //overrule default view, force to specified location
        if (urlParams.has('lat') && urlParams.has('lon')) {
            const geopos = new L.LatLng(urlParams.get('lat'), urlParams.get('lon'));
            L.circle(geopos, 500, {
                color: 'red',
                fillColor: '#f03',
                fillOpacity: 0,
                weight: 6
            }).addTo(map);
            map.setView(geopos, 13)
        }

        //attributes for basemap credit (lower right hand corner annotation)
        var streetsAttr = 'Map tiles by Carto, under CC BY 3.0. Data by <a href="https://openstreetmap.org">OpenStreetMap</a>, under ODbL.';
        var OpenStreetMap_MapnikAttr = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

        var streets = L.tileLayer('https://cartodb-basemaps-{s}.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png', {
            id: 'MapID',
            attribution: streetsAttr
        }).addTo(map);

        var OpenStreetMap_Mapnik = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            id: 'MapID',
            attribution: OpenStreetMap_MapnikAttr
        });

        //create baseMaps variable to store basemap layer switcher
        var baseMaps = {
            "Carto Streets": streets,
            "OpenStreetMap": OpenStreetMap_Mapnik,
        };

        var masClusGroup = new L.markerClusterGroup({
            disableClusteringAtZoom: 7,
            chunkedLoading: true
        }).addTo(map);

        var layerSpaceApi = L.featureGroup.subGroup(masClusGroup).addTo(map);
        loadGeoJSON('/api.geojson', layerSpaceApi, 'api');

        var layerSpaceFablab = L.featureGroup.subGroup(masClusGroup).addTo(map);
        loadGeoJSON('/fablab.geojson', layerSpaceFablab, 'fablab');
        loadGeoJSON('/fablabq.geojson', layerSpaceFablab, 'fablab');

        var layerSpaceWiki = L.featureGroup.subGroup(masClusGroup).addTo(map);
        loadGeoJSON('/wiki.geojson', layerSpaceWiki, '');

        var overLayMap = {
            "Hackerspace (SpaceAPI)": layerSpaceApi,
            "FabLab.io": layerSpaceFablab,
            "Hackerspace (wiki)": layerSpaceWiki,
        };

        L.control.layers(baseMaps, overLayMap).addTo(map);

        var poiLayers = L.layerGroup([
                layerSpaceApi,
                layerSpaceFablab,
                layerSpaceWiki
            ])
            .addTo(map);

        L.control.search({
                layer: poiLayers,
                initial: false,
                propertyName: 'name',
                minLength: 2,
                buildTip: function(text, val) {
                    var type = val.layer.feature.properties.sourcetype;
                    var url = val.layer.feature.properties.source;
                    var city = val.layer.feature.properties.city;
                    return '<a href="#" class="' + url + '">' + text + ' - ' + city + '  (' + type + ')</a>';
                },
                moveToLocation: function(latlng, title, map) {
                    map.setView(latlng, 8); // access the zoom
                }
            })
            .addTo(map);


        $(document).ajaxComplete(function(event, xhr, settings) {
            map.spin(false);
        });

        //Callback geolocation
        function getPos(geoPos) {
            map.setView([geoPos.coords.latitude, geoPos.coords.longitude], 4)
        };

        function loadGeoJSON(geofile, geolayer, type) {
            $.getJSON(geofile, function(cartodbdata) {
                geojsonlayer = L.geoJson(cartodbdata, {
                    onEachFeature: function(feature, layer) {
                        if (feature.properties.name) {
                            var html = '<b>' + feature.properties.name + '</b><br/>' +
                                feature.properties.address + '<br/>' +
                                feature.properties.zip + ' ' + feature.properties.city + '<br/>' +
                                "<a href='" + feature.properties.url + "' target='_blank' >website</a>  " +
                                "<a href='" + feature.properties.source + "' target='_blank' >source</a>  "
                            if (type == 'api') {
                                html += "<a href='/heatmap/show.php?id=" + feature.properties.name + "' target='_blank' >heatmap</a>"
                            }
                            layer.bindPopup(html).addTo(geolayer);
                        };
                    },
                    pointToLayer: function(feature, latlon) {
                        var iconurl = feature.properties['marker-symbol'];
                        var zIndex = 100;
                        if (type == 'api') {
                            zIndex = 500;
                        } else if (type == 'fablab') {
                            zIndex = 100;
                        } else {
                            zIndex = 200;
                        }
                        return new L.Marker(latlon, {
                            icon: new L.icon({
                                iconUrl: iconurl,
                                iconSize: [30, 70],
                                iconAnchor: [15, 35],
                                popupAnchor: [0, -25]
                            }),
                            zIndexOffset: zIndex
                        });
                    }
                });
            });

        };
    </script>
    <div class="legend">
        <ul>
            <li>Source </li>
            <li><img src="/image/hs_open.png" alt="source spaceapi">API open</li>
            <li><img src="/image/hs_closed.png" alt="source spaceapi">API closed</li>
            <li><img src="/image/hs.png" alt="source spaceapi">API (unknown)</li>
            <li><img src="/image/fablab.png" alt="source fablab.oi">Fablab</li>
            <li><img src="/image/hs_black.png" alt="source wiki.hackerspaces.org">Wiki</li>
        </ul>
    </div>
</body>

</html>