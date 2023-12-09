-- setup tables for mapall database
-- apply these manual
CREATE IF NOT EXISTS DATABASE mapall;
USE mapall; 

CREATE IF NOT EXISTS TABLE heatmspaces
(
    `key`         varchar(32)          not null
        primary key,
    name          varchar(256)         null,
    url           mediumtext           null,
    logo          mediumtext           not null,
    get_ok        int(6)     default 0 not null,
    get_err       int(6)     default 0 not null,
    get_total     int(6)     default 0 not null,
    sa            mediumtext           not null,
    lns           tinyint(1) default 0 not null,
    timezone      varchar(32)          null,
    timezone_long mediumtext           null,
    offset        int        default 0 not null,
    lat           float                null,
    lon           float                null,
    lastupdated   datetime             null
);

CREATE IF NOT EXISTS TABLE mapspace
(
    source          varchar(1)   not null,
    sourcekey       varchar(250) not null,
    lon             float        null,
    lat             float        null,
    name            varchar(250) null,
    lastcurlerror   int          null,
    curlerrorcount  int          null,
    lastdataupdated int          not null,
    primary key (source, sourcekey)
)

CREATE IF NOT EXISTS TABLE wikispace
(
    wikiurl         varchar(500) not null
        primary key,
    name            varchar(150) null,
    lastcurlerror   int          null,
    lastdataupdated datetime     not null,
    status          varchar(10)  null
)

