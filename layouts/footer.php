</div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
  <script type="text/javascript" src="libs/js/functions.js"></script>

  <!-- ✅ STRICT SINGLE-ACCORDION & AUTO-RESET SCRIPT -->
<script>
$(document).ready(function(){

    // A. functions.js ke purane click handlers ko remove karo
    $('.submenu-toggle').off('click');
    $(document).off('click', '.submenu-toggle');

    // B. Page load par pehle saare submenus ko HIDE karo
    $('.submenu').hide();
    $('.arrow').removeClass('rotate');

    // C. Sirf tabhi open karo agar active tab me khola tha
    var activeMenu = sessionStorage.getItem('active_sidebar_menu');
    if (activeMenu) {
        var $activeToggle = $('.submenu-toggle[data-menu="' + activeMenu + '"]');
        if ($activeToggle.length) {
            $activeToggle.next('.submenu').show();
            $activeToggle.find('.arrow').addClass('rotate');
        }
    }

    // D. Strict Single-Accordion Logic
    $(document).on('click', '.submenu-toggle', function(e){
        e.preventDefault();
        e.stopPropagation();

        var $this = $(this);
        var $targetSubmenu = $this.next('.submenu');
        var $targetArrow = $this.find('.arrow');
        var menuKey = $this.attr('data-menu');

        var isAlreadyOpen = $targetSubmenu.is(':visible');

        // Baki saare open submenus ko fauran close karo
        $('.submenu').not($targetSubmenu).slideUp(200);
        $('.submenu-toggle').not($this).find('.arrow').removeClass('rotate');

        if (isAlreadyOpen) {
            $targetSubmenu.slideUp(200);
            $targetArrow.removeClass('rotate');
            sessionStorage.removeItem('active_sidebar_menu');
        } else {
            $targetSubmenu.slideDown(200);
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

</script>
</body>
</html>

<?php if(isset($db)) { $db->db_disconnect(); } ?>
