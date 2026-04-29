const searchInput   = document.getElementById('live-search');
const searchResults = document.getElementById('search-results');

if (searchInput) {
    let debounceTimer;

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }

        debounceTimer = setTimeout(async () => {
            const res  = await fetch(`${BASE_PATH}/pets/live-search?q=${encodeURIComponent(query)}`);
            const pets = await res.json();

            if (pets.length === 0) {
                searchResults.innerHTML = '<div class="search-dropdown__item">No pets found</div>';
            } else {
                searchResults.innerHTML = pets.map(pet => `
                    <a href="${BASE_PATH}/pets/${pet.id}" class="search-dropdown__item">
                        <img src="${pet.image ? BASE_PATH + pet.image : BASE_PATH + '/Assets/img/default-pet.jpg'}" alt="${pet.name}">
                        <span>${pet.name} <small>${pet.breed}</small></span>
                    </a>
                `).join('');
            }

            searchResults.classList.remove('hidden');
        }, 300);
    });

    document.addEventListener('click', e => {
        if (!searchInput.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });
}

document.querySelectorAll('.flash').forEach(el => {
    setTimeout(() => el.style.opacity = '0', 4000);
    setTimeout(() => el.remove(), 4500);
});

const petSearch = document.getElementById("petSearch");
const petCards = document.querySelectorAll(".pet-card");

if (petSearch) {
    petSearch.addEventListener("input", function () {
        const searchValue = petSearch.value.toLowerCase();

        petCards.forEach(function (card) {
            const text = card.innerText.toLowerCase();

            if (text.includes(searchValue)) {
                card.style.display = "block";
            } else {
                card.style.display = "none";
            }
        });
    });
}

// function answerQuestion() {
//     const answer = document.getElementById("aiAnswer");

//     answer.textContent =
//         "";
// }