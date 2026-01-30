document.addEventListener("DOMContentLoaded", function() {
    fetch("/api/dashboard/data")
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Données du tableau de bord:", data);
            document.getElementById("api-response").innerText = JSON.stringify(data, null, 2);
        })
        .catch(error => {
            console.error("Erreur lors de la récupération des données:", error);
            document.getElementById("api-response").innerText = "Erreur: " + error.message;
        });
});

