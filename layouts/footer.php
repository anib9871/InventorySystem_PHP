</div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
  <script type="text/javascript" src="libs/js/functions.js"></script>

  <!-- ✅ FIXED NESTED ACCORDION SCRIPT -->
<script>
$(document).ready(function(){

    // 1. Purane global click handlers unbind karo
    $('.submenu-toggle').off('click');
    $(document).off('click', '.submenu-toggle');

    // 2. Initial State: Sabhi submenus ko default hide rakho
    $('.submenu').hide();
    $('.arrow').removeClass('rotate');

    // 3. Purana open menu restore karo (Session storage se)
    var activeMenu = sessionStorage.getItem('active_sidebar_menu');
    if (activeMenu) {
        var $activeToggle = $('.submenu-toggle[data-menu="' + activeMenu + '"]');
        if ($activeToggle.length) {
            // Self + Parents (Level 1 & Level 2) dono ko show karo
            $activeToggle.parents('.submenu').show();
            $activeToggle.parents('li').find('> .submenu-toggle > .arrow').addClass('rotate');
            $activeToggle.next('.submenu').show();
            $activeToggle.find('> .arrow').addClass('rotate');
        }
    }

    // 4. Strict Level-Based Accordion Click
    $(document).on('click', '.submenu-toggle', function(e){
        e.preventDefault();
        e.stopPropagation();

        var $this = $(this);
        var $targetSubmenu = $this.next('.submenu');
        var $targetArrow = $this.find('.arrow');
        var menuKey = $this.attr('data-menu');

        var isAlreadyOpen = $targetSubmenu.is(':visible');

        // 🔥 FIX: Parent menu ko bina chede, SIRF same level ke baki sibling menus ko close karo
        $this.closest('li').siblings().find('> .submenu').slideUp(150);
        $this.closest('li').siblings().find('> a .arrow').removeClass('rotate');

        if (isAlreadyOpen) {
            $targetSubmenu.slideUp(150);
            $targetArrow.removeClass('rotate');
            if (menuKey) {
                sessionStorage.removeItem('active_sidebar_menu');
            }
        } else {
            $targetSubmenu.slideDown(150);
            $targetArrow.addClass('rotate');
            if (menuKey) {
                sessionStorage.setItem('active_sidebar_menu', menuKey);
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

            window.location =
            'delete_grn.php?bill=' + encodeURIComponent(bill);

        }

    });

}

<!-- ✅ AUTO-LOGOUT ON TAB / BROWSER CLOSE -->

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
