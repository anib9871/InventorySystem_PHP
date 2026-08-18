</div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="libs/js/functions.js"></script>

    <!-- ✅ MOBILE TOGGLE & NESTED ACCORDION SCRIPT -->
    <script>
    $(document).ready(function() {

        // 1. Mobile Sidebar Open/Close Toggle
        $(document).on('click', '#menuToggle, .logo', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('.sidebar').toggleClass('show-sidebar');
            $('body').toggleClass('sidebar-open');
        });

        // Sidebar ke andar click karne par drawer band na ho
        $(document).on('click', '.sidebar', function(e) {
            e.stopPropagation();
        });

        // Screen ke bahar tap karne par sidebar band ho
        $(document).on('click', function(e) {
            if ($('body').hasClass('sidebar-open') || $('.sidebar').hasClass('show-sidebar')) {
                $('.sidebar').removeClass('show-sidebar');
                $('body').removeClass('sidebar-open');
            }
        });

        // 2. Submenu Dropdown & Arrow Rotation Toggle
        $(document).off('click', '.submenu-toggle, .sidebar li > a');
        $(document).on('click', '.submenu-toggle, .sidebar li > a', function(e) {
            var $this = $(this);
            var $submenu = $this.next('.nav-submenu, .submenu, ul');

            // Agar submenu exist karta hai
            if ($submenu.length > 0) {
                e.preventDefault();
                e.stopPropagation();

                var $arrow = $this.find('.arrow, .fa-chevron-right, .fa-angle-right, .glyphicon-chevron-right');

                // Dusre open sibling menus ko close karo
                $this.closest('li').siblings().find('.nav-submenu, .submenu, ul').slideUp(200);
                $this.closest('li').siblings().find('.arrow, .fa-chevron-right, .fa-angle-right, .glyphicon-chevron-right').removeClass('rotate');
                $this.closest('li').siblings().find('> a').removeClass('open');

                // Current submenu toggle karo
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
