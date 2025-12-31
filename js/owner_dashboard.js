

document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebar");
    const toggleBtn = document.getElementById("toggleSidebar");

    if (toggleBtn) {
        toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("collapsed");
        });
    }
    const menuLinks = document.querySelectorAll(".sidebar-menu li a");
    const currentPage = window.location.pathname.split("/").pop();
    menuLinks.forEach(link => {
        if (link.getAttribute("href") === currentPage) {
            link.parentElement.classList.add("active");
        } else {
            link.parentElement.classList.remove("active");
        }
    });
    document.querySelectorAll(".stat-card").forEach(card => {
        card.addEventListener("mouseenter", () => {
            card.style.transform = "translateY(-6px)";
            card.style.transition = "0.3s ease";
        });

        card.addEventListener("mouseleave", () => {
            card.style.transform = "translateY(0)";
        });
    });
    document.querySelectorAll(".activity-item").forEach(item => {
        item.addEventListener("mouseenter", () => {
            item.style.background = "#f9fafb";
        });

        item.addEventListener("mouseleave", () => {
            item.style.background = "transparent";
        });
    });
    document.querySelectorAll(".vehicle-card").forEach(card => {
        card.addEventListener("click", () => {
            card.style.boxShadow = "0 6px 16px rgba(0,0,0,0.15)";
            setTimeout(() => {
                card.style.boxShadow = "";
            }, 200);
        });
    });
    document.querySelectorAll(".action-card").forEach(action => {
        action.addEventListener("click", () => {
            action.style.transform = "scale(0.96)";
            setTimeout(() => {
                action.style.transform = "scale(1)";
            }, 150);
        });
    });
    window.addEventListener("resize", () => {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove("collapsed");
        }
    });

});
