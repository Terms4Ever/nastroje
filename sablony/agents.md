# <Projekt> - pokyny pro agenty

<Jedna až dvě věty: co projekt je, pro koho běží a kde.>

Obecná pravidla (commity, README, dokumentace, issues, migrace) jsou
v `~/.claude/CLAUDE.md` a nepřepisují se sem. Tenhle soubor říká jen to, co je
vlastní tomuhle projektu.

## Stack a struktura

<Jazyk a verze, framework, databáze, nasazení. Pak tři až deset cest, které
agent potřebuje najít hned: kde je vstupní bod, kde doménová logika, kde
šablony, kde testy.>

## Doménová pravidla

<Co se nesmí porušit, protože to rozbije data nebo peníze uživatele. Pravidlo
na řádek, vždy s důvodem. Sem patří i přejmenování a historické názvy, ať se
agent nespálí o starý název ve větvi nebo v datech.>

## Brány před commitem

<Příkazy, které musí projít, každý na vlastním řádku, v pořadí, v jakém se
pouštějí. Co se nesmí obcházet a proč.>

```bash
```

## Nasazení

<Jak se to dostane na produkci: ručně, workflow, který soubor. Co se nasazením
NEnahrává. Kde se pozná, že nasazení prošlo.>

## Jak se domlouváme

<Jazyk komunikace, co zadavatel rozhoduje sám a co si agent rozhodne. Kam se
píše hotová práce: deník, stav projektu, komentář u issue.>
