/* Pi-hole: A black hole for Internet advertisements
*  (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*  Network-wide ad blocking via your own hardware.
*
*  This file is copyright under the latest version of the EUPL.
*  Please see LICENSE file for your rights under this license. */
// Setup
$.ajaxSetup({cache: false});
const token = $("#token").html();

const actionButtonHandle = function (event) {
    let ipAddress = $(event.target).closest('li')[0].id;
    if (this.name === 'delete') {
        removeIPAddress(ipAddress);
    }
    else {
        updateIpAddress(ipAddress);
    }
};
const buildRow = function (entry, index) {
    this.append(
        "<li id=\"" + entry['ip'] + "\"  class=\"list-group-item clearfix\"><span style=\"font-weight:bold\">"
        + entry['ip']
        + "</span>: <span id=\"" + entry['ip']  + "-domains\">" + entry['domains'].join(', ') + "</span>"
        + "<button class=\"btn btn-danger btn-xs pull-right\" type=\"button\" name=\"delete\"><span class=\"glyphicon glyphicon-trash\"></span></button>"
        + "<button class=\"btn btn-warning btn-xs pull-right\" style=\"margin-right: 1em\" name=\"edit\" type=\"button\"><span class=\"glyphicon glyphicon-pencil\"></span></button>"
        + "</li>");
};
const sortByIpAddress = function (a, b) {
    if (a.ip < b.ip) return -1;
    if (a.ip > b.ip) return 1;
    return 0;
};

function refresh(fade) {
    let listw;
    let list = $("#list");
    if(fade) {
        list.fadeOut(100);
        if(listw) {
            listw.fadeOut(100);
        }
    }
    $('#domains').val('');
    $('#ipaddress').val('');
    $('#btnAdd').text('Add');
    $.ajax({
        url: "scripts/pi-hole/php/get-domainlist.php",
        method: "get",
        success: function(response) {
            list.html('');
            if(listw) {
                listw.html('');
            }
            let data = JSON.parse(response);
            data.sort(sortByIpAddress);

            if(data.length === 0) {
                $('h3').hide();
                let listElement = listw ? listw : list;
                listElement.html("<div class=\"alert alert-info\" role=\"alert\">Your Domain List is empty!</div>");
            }
            else
            {
                $("h3").show();
                data.forEach(buildRow.bind(list));
                $("#list button").click("button", actionButtonHandle);
            }
            list.fadeIn(100);
            if(listw) {
                listw.fadeIn(100);
            }
        },
        error: function(jqXHR, exception) {
            $("#alFailure").show();
        }
    });
}

function removeIPAddress(ipAddress) {
    let ipRow = $("#list #"+ipAddress);
    ipRow.hide("highlight");

    clearMessages('Deleting local domain');

    $.ajax({
        url: "scripts/pi-hole/php/remove-local-ip.php",
        method: "post",
        data: {"ip":ipAddress, "token":token},
        success: function(response) {
            if(response.length !== 0){
                return;
            }
            ipRow.remove();
            showSuccess(ipAddress + " removed");
        },
        error: function(jqXHR, exception) {
            let response = $.parseJSON(jqXHR.responseText);
            showError(jqXHR.status, response.message);
            ipRow.show({queue:true});
        }
    });
}

function updateIpAddress(ipAddress) {
    let domains = $('#' + CSS.escape(ipAddress) + '-domains').text();

    $('#ipaddress').val(ipAddress);
    $('#domains').val(domains);

    $('#btnAdd').text('Update');
}

function addIpAddress() {
    let domains = $('#domains');
    let ipaddress = $('#ipaddress');
    if(domains.val().length === 0 || ipaddress.val().length === 0) {
        return;
    }
    clearMessages('Adding to the domain list');

    $.ajax({
        url: "scripts/pi-hole/php/update-domainlist.php",
        method: "post",
        data: {"ip":ipaddress.val().trim(), "domains":domains.val().trim(), "autoadd": $('#autoadd').prop('checked'), "token":token},
        success: function(response, textStatus, c) {
            if (response.status === 201) {
                successMsg = 'Successfully created a new local domain for ' + ipaddress.val();
            } else {
                successMsg = 'Successfully modified the domains for ' + ipaddress.val();
            }
            showSuccess(successMsg);
            refresh(true);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            let response = $.parseJSON(jqXHR.responseText);
            showError(jqXHR.status, response.message);
        }
    });
}

function clearMessages(msg)
{
    $('#infoMsg').text(msg);
    $('#alInfo').show();
    $('#successMsg').hide();
    $('#alFailure').hide();
    $('#alWarning').hide();
}

function showSuccess(successMsg) {
    let alSuccess = $('#alSuccess');
    let successMsgEl = $('#successMsg');
    let alInfo = $('#alInfo');

    successMsgEl.text(successMsg);

    alSuccess.show();
    alSuccess.delay(1000).fadeOut(2000, function() {
        alSuccess.hide();
    });
    alInfo.delay(1000).fadeOut(2000, function() {
        alInfo.hide();
    });
}

function showError(status, message) {
    let alFailure = $('#alFailure');
    let alWarning = $('#alWarning');
    let alInfo = $('#alInfo');
    let err = $('#err');
    let warn = $('#warn');

    if (parseInt(status / 100, 10) === 4) {
        alWarning.show();
        warn.html(message);
        alWarning.delay(8000).fadeOut(2000, function() {
            alWarning.hide();
        });
        alInfo.delay(8000).fadeOut(2000, function() {
            alInfo.hide();
        });
    } else {
        alFailure.show();
        err.html(message);
        alFailure.delay(8000).fadeOut(2000, function() {
            alFailure.hide();
        });
        alInfo.delay(8000).fadeOut(2000, function() {
            alInfo.hide();
        });
    }
}

// Handle enter button for adding domains
const ENTER_BUTTON_CODE = 13;
$(document).keypress(function(e) {
    if(e.which === ENTER_BUTTON_CODE && $("#domain").is(":focus")) {
        addIpAddress();
    }
});

// Handle buttons
$("#btnAdd").click(addIpAddress);
$("#btnRefresh").click(function() { refresh(true); });

// Handle hiding of alerts
$(function(){
    $("[data-hide]").on("click", function(){
        $(this).closest("." + $(this).attr("data-hide")).hide();
    });
});

// Wrap form-group's buttons to next line when viewed on a small screen
$(window).on("resize",function() {
    if ($(window).width() < 991) {
        $(".form-group.input-group").removeClass("input-group").addClass("input-group-block");
        $(".form-group.input-group-block > input").css("margin-bottom", "5px");
        $(".form-group.input-group-block > .input-group-btn").removeClass("input-group-btn").addClass("btn-block text-center");
    }
    else {
        $(".form-group.input-group-block").removeClass("input-group-block").addClass( "input-group" );
        $(".form-group.input-group > input").css("margin-bottom","");
        $(".form-group.input-group > .btn-block.text-center").removeClass("btn-block text-center").addClass("input-group-btn");
    }
});
$(document).ready(function() {
    $(window).trigger("resize");
});
window.onload = refresh(false);
