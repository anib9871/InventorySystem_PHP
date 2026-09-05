</div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="libs/js/functions.js"></script>

    <!-- ✅ AUTO ACTIVE MENU RETENTION + ACCORDION SCRIPT -->
    <script>
    $(document).ready(function() {

        // ================= 1. AUTO OPEN CURRENT ACTIVE MENU =================
        var currentUrl = window.location.pathname.split("/").pop(); // e.g. "customer_master.php"
        
        if (currentUrl === "" || currentUrl === "index.php") {
            currentUrl = "admin.php";
        }

        // Sidebar ke saare links check karo jo current URL se match karte hain
        $('.sidebar ul li a').each(function() {
            var href = $(this).attr('href');
            if (href && href.indexOf(currentUrl) !== -1 && currentUrl !== "") {
                // Active link highlight
                $(this).closest('li').addClass('active');
                
                // Parent submenus ko open rakho
                var $parentSubmenu = $(this).closest('.nav-submenu, .submenu, ul');
                $parentSubmenu.show(); // Submenu open
                
                var $parentToggle = $parentSubmenu.prev('a');
                $parentToggle.addClass('open');
                $parentToggle.find('.arrow, .fa-chevron-right, .fa-angle-right, .glyphicon-chevron-right').addClass('rotate');

                // Agar 2-level nested submenu ho
                var $superParent = $parentSubmenu.parents('.nav-submenu, .submenu, ul');
                if ($superParent.length) {
                    $superParent.show();
                    $superParent.prev('a').addClass('open').find('.arrow, .fa-chevron-right, .fa-angle-right, .glyphicon-chevron-right').addClass('rotate');
                }
            }
        });

        // ================= 2. MOBILE DRAWER TOGGLE =================
        $(document).on('click', '#menuToggle, .logo', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('.sidebar').toggleClass('show-sidebar');
            $('body').toggleClass('sidebar-open');
        });

        // Sidebar ke andar click par drawer close na ho
        $(document).on('click', '.sidebar', function(e) {
            e.stopPropagation();
        });

        // Bahar click par mobile drawer band ho
        $(document).on('click', function(e) {
            if ($('body').hasClass('sidebar-open') || $('.sidebar').hasClass('show-sidebar')) {
                $('.sidebar').removeClass('show-sidebar');
                $('body').removeClass('sidebar-open');
            }
        });

        // ================= 3. SUBMENU CLICK ACCORDION =================
        $(document).off('click', '.submenu-toggle, .sidebar li > a');
        $(document).on('click', '.submenu-toggle, .sidebar li > a', function(e) {
            var $this = $(this);
            var $submenu = $this.next('.nav-submenu, .submenu, ul');

            // Agar dropdown menu hai (page link nahi hai)
            if ($submenu.length > 0) {
                e.preventDefault();
                e.stopPropagation();

                var $arrow = $this.find('.arrow, .fa-chevron-right, .fa-angle-right, .glyphicon-chevron-right');

                // Sibling open menus ko band karo
                $this.closest('li').siblings().find('.nav-submenu, .submenu, ul').slideUp(200);
                $this.closest('li').siblings().find('.arrow, .fa-chevron-right, .fa-angle-right, .glyphicon-chevron-right').removeClass('rotate');
                $this.closest('li').siblings().find('> a').removeClass('open');

                // Current menu toggle karo
                if ($submenu.is(':visible')) {
                    $submenu.slideUp(200);
                    $arrow.removeClass('rotate');
                    $this.removeClass('open');
                } else {
                    $submenu.slideDown(200);
                    $arrow.addClass('rotate');
                    $this.addClass('open');
                }
            }
        });

    });
    </script>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        if (typeof flatpickr !== "undefined" && document.querySelector("#bill_date")) {
            flatpickr("#bill_date", {
                dateFormat: "d/M/Y",
                allowInput: false,
                disableMobile: true
            });
        }
    });
    </script>
<script>

/*
|--------------------------------------------------------------------------
| DATABASE BACKUP
|--------------------------------------------------------------------------
*/

async function openDatabaseBackup() {

    /*
    |--------------------------------------------------------------------------
    | BROWSER SUPPORT CHECK
    |--------------------------------------------------------------------------
    */

    if (!window.showSaveFilePicker) {

        Swal.fire({

            icon: 'warning',

            title: 'Browser Not Supported',

            html:
                'Your browser does not support direct folder selection.' +
                '<br><br>' +
                '<strong>Please use Google Chrome or Microsoft Edge.</strong>',

            confirmButtonColor: '#a80000'

        });

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | CONFIRMATION POPUP
    |--------------------------------------------------------------------------
    */

    const result = await Swal.fire({

        title: 'Create Database Backup?',

        html:
            '<div style="font-size:14px;line-height:1.7;">' +

            'A complete backup of your organization database ' +
            'will be created.' +

            '<br><br>' +

            '<strong>You will be asked where you want to save the backup.</strong>' +

            '<br><br>' +

            'You can continue using Storely while the backup is being prepared.' +

            '</div>',

        icon: 'question',

        showCancelButton: true,

        confirmButtonText:
            '<i class="fa-solid fa-database"></i> Continue',

        cancelButtonText: 'Cancel',

        confirmButtonColor: '#a80000',

        cancelButtonColor: '#64748b',

        reverseButtons: true,

        allowOutsideClick: false

    });


    if (!result.isConfirmed) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | FILE NAME
    |--------------------------------------------------------------------------
    */

    const now = new Date();

    const pad = (num) =>
        String(num).padStart(2, '0');

    const filename =
        'storely_backup_' +

        now.getFullYear() + '-' +

        pad(now.getMonth() + 1) + '-' +

        pad(now.getDate()) + '_' +

        pad(now.getHours()) + '-' +

        pad(now.getMinutes()) + '-' +

        pad(now.getSeconds()) +

        '.sql';


    /*
    |--------------------------------------------------------------------------
    | ASK USER WHERE TO SAVE
    |--------------------------------------------------------------------------
    */

    let fileHandle;

    try {

        fileHandle = await window.showSaveFilePicker({

            suggestedName: filename,

            types: [

                {
                    description: 'SQL Database Backup',

                    accept: {
                        'application/sql': ['.sql']
                    }
                }

            ]

        });

    } catch (error) {

        /*
        |--------------------------------------------------------------------------
        | USER CANCELLED FILE PICKER
        |--------------------------------------------------------------------------
        */

        if (error.name === 'AbortError') {
            return;
        }

        Swal.fire({

            icon: 'error',

            title: 'Save Location Error',

            text: 'Unable to select the backup location.',

            confirmButtonColor: '#a80000'

        });

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | BACKUP STATUS PANEL
    |--------------------------------------------------------------------------
    */

    Swal.fire({

        toast: true,

        position: 'bottom-end',

        title: 'Creating Database Backup...',

        html:
            '<div style="margin-top:8px;">' +

            '<div id="backupStatusText">' +
            'Connecting to database...' +
            '</div>' +

            '<div style="' +
            'margin-top:10px;' +
            'font-size:12px;' +
            'color:#64748b;' +
            '">' +

            'You can continue using Storely.' +

            '</div>' +

            '</div>',

        showConfirmButton: false,

        allowOutsideClick: false,

        allowEscapeKey: false,

        width: '380px'

    });


    /*
    |--------------------------------------------------------------------------
    | OPEN FILE
    |--------------------------------------------------------------------------
    */

    let writable;

    try {

        writable =
            await fileHandle.createWritable();

    } catch (error) {

        Swal.fire({

            icon: 'error',

            title: 'File Error',

            text:
                'The selected location could not be opened.',

            confirmButtonColor: '#a80000'

        });

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | START ASYNC BACKUP REQUEST
    |--------------------------------------------------------------------------
    */

    try {

        const response =
            await fetch('database_backup.php', {

                method: 'GET',

                credentials: 'same-origin',

                cache: 'no-store'

            });


        if (!response.ok) {

            throw new Error(
                'Backup server returned ' +
                response.status
            );
        }


        /*
        |--------------------------------------------------------------------------
        | STREAM RESPONSE
        |--------------------------------------------------------------------------
        */

        if (!response.body) {

            throw new Error(
                'Streaming is not supported.'
            );
        }


        const reader =
            response.body.getReader();


        /*
        |--------------------------------------------------------------------------
        | WRITE STREAM
        |--------------------------------------------------------------------------
        */

        let totalBytes = 0;

        let lastUpdate = Date.now();


        while (true) {

            const {
                done,
                value
            } = await reader.read();


            if (done) {
                break;
            }


            if (value) {

                await writable.write(value);

                totalBytes += value.length;


                /*
                |--------------------------------------------------------------------------
                | STATUS UPDATE
                |--------------------------------------------------------------------------
                */

                if (
                    Date.now() - lastUpdate > 1000
                ) {

                    const mb =
                        (
                            totalBytes /
                            1024 /
                            1024
                        ).toFixed(1);


                    const status =
                        document.getElementById(
                            'backupStatusText'
                        );


                    if (status) {

                        status.innerHTML =
                            'Backup data received: ' +
                            mb +
                            ' MB';

                    }


                    lastUpdate =
                        Date.now();
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CLOSE FILE
        |--------------------------------------------------------------------------
        */

        await writable.close();


        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        Swal.fire({

            toast: true,

            position: 'bottom-end',

            icon: 'success',

            title: 'Backup Completed',

            text:
                'Database backup saved successfully.',

            showConfirmButton: false,

            timer: 5000,

            timerProgressBar: true

        });


    } catch (error) {

        /*
        |--------------------------------------------------------------------------
        | ABORT / CLOSE FILE
        |--------------------------------------------------------------------------
        */

        try {
            await writable.abort();
        } catch (e) {}


        console.error(
            'Database Backup Error:',
            error
        );


        Swal.fire({

            icon: 'error',

            title: 'Backup Failed',

            html:
                'The database backup could not be completed.' +

                '<br><br>' +

                '<small>' +
                'Please try again.' +
                '</small>',

            confirmButtonColor: '#a80000'

        });

    }

}

</script>

    <script>
    function deleteGRN(bill){
        Swal.fire({
            title: 'Delete GRN?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Delete'
        }).then((result) => {
            if(result.isConfirmed){
                window.location = 'delete_grn.php?bill=' + encodeURIComponent(bill);
            }
        });
    }

    // AUTO-LOGOUT ON TAB / BROWSER CLOSE
    window.addEventListener("pagehide", function (event) {
        if (!sessionStorage.getItem("is_navigating")) {
            sessionStorage.clear();
        }
    });

    document.addEventListener("click", function (e) {
        sessionStorage.setItem("is_navigating", "true");
    });

    document.addEventListener("submit", function (e) {
        sessionStorage.setItem("is_navigating", "true");
    });

    window.addEventListener("load", function () {
        sessionStorage.removeItem("is_navigating");
    });
    </script>

</body>
</html>

<?php if(isset($db)) { $db->db_disconnect(); } ?>
