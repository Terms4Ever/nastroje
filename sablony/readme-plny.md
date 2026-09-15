# 🧾 Název Projektu

**Jedna věta, která řekne, co to je**

Odstavec pro člověka, který sem přišel poprvé. Co aplikace dělá, pro koho je
a čím se liší. Tři až pět řádků, ne víc — kdo chce podrobnosti, čte dál.

![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MariaDB-11.2+-003545?logo=mariadb&logoColor=white)
![License](https://img.shields.io/badge/license-proprietary-red)

---

## ✨ Hlavní funkce

### Oblast první

- Co to umí, v odrážkách
- Každá odrážka jedna schopnost

### Oblast druhá

- Podsekce použij, jen když je funkcí hodně
- U menšího projektu stačí jeden seznam

---

## 🛠️ Tech Stack

| Vrstva     | Technologie          |
|------------|----------------------|
| Backend    | PHP 8.3              |
| Databáze   | MariaDB 11.2         |
| Frontend   | Bootstrap 5.3        |
| Platby     | Stripe               |

Verze piš tak, jak je opravdu používáš. Číslo, které zastará, je horší než
žádné — a kontrola ti ho jednou vytkne.

---

## 📁 Struktura projektu

```
projekt/
├── src/              # jádro aplikace
├── views/            # šablony
├── public/           # to, co vidí web
└── tests/            # testy
```

Každá cesta, kterou tu vypíšeš, musí existovat. Kontrola to ověřuje.

---

## 🚀 Instalace (lokální vývoj)

```bash
# 1. Klonování
git clone https://github.com/Terms4Ever/nazev-projektu.git
cd nazev-projektu

# 2. Závislosti
composer install

# 3. Konfigurace
# Vytvoř config.local.php (je v .gitignore) a přepiš v něm, co je potřeba.
# Ostré klíče do verzovaného config.php nepatří.

# 4. Databáze
mysql -u root -p nazev < db/schema.sql

# 5. Spuštění
php -S 127.0.0.1:8000
```

Popiš postup tak, aby podle něj projekt rozjel někdo, kdo ho nezná. Soubory,
které v návodu jmenuješ, musí v repozitáři být — jinak kontrola neprojde.

---

## 📦 Nasazení

**Jak se to dostane na produkci.** Push do `main` spustí GitHub Actions, které
nahrají soubory na server. Vypiš, co se z nasazení vynechává a proč.

**Co běží pravidelně.** Cron, fronty, webhooky. U každého řekni, čím se spouští
a kam loguje.

**Co se musí nastavit na serveru.** Proměnné prostředí, klíče, oprávnění.
Hodnoty sem nepiš, jen názvy.

---

## 📄 Licence

Proprietární software. Veškerá práva vyhrazena.
