<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Emploi du temps {{ $annee }} · {{ $nom }}</title>
    <style>
        @page { margin: 22px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #2D2B45; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .sous-titre { color: #6E6B85; font-size: 10px; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { background: #F7F7FB; color: #6E6B85; font-weight: normal; padding: 5px 4px; border: 1px solid #E2E1EE; }
        td { border: 1px solid #E2E1EE; padding: 3px; vertical-align: top; }
        .creneau { width: 72px; font-weight: bold; padding: 5px; }
        .midi { display: block; font-weight: normal; color: #6E6B85; }
        .recreation td { background: #F7F7FB; color: #6E6B85; text-align: center; padding: 3px; }
        .pas-de-cours { background: #EEEEF3; color: #6E6B85; text-align: center; vertical-align: middle; }
        .seance { border-radius: 4px; padding: 3px 4px; margin-bottom: 3px; }
        .seance .titre { font-weight: bold; }
        .seance .matiere { color: #6E6B85; }
    </style>
</head>
<body>
    <h1>Emploi du temps · {{ $libelle }} : {{ $nom }}</h1>
    <p class="sous-titre">{{ $ecole }} · année scolaire {{ $annee }}</p>

    <table>
        <thead>
            <tr>
                <th class="creneau">Créneau</th>
                @foreach ($jours as $jour)
                    <th>{{ $jour }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($lignes as $ligne)
                @if ($ligne['recreation'])
                    <tr class="recreation"><td colspan="7">{{ $ligne['libelle'] }}</td></tr>
                @else
                    <tr>
                        <td class="creneau">
                            {{ $ligne['libelle'] }}
                            @if ($ligne['midi'])<span class="midi">Midi</span>@endif
                        </td>
                        @foreach ($ligne['cases'] as $case)
                            @if ($case === null)
                                <td class="pas-de-cours">Pas de cours</td>
                            @else
                                <td>
                                    @foreach ($case as $seance)
                                        <div class="seance" style="background: #{{ $couleurs[$seance['type']] }}">
                                            <div class="titre">{{ $seance['titre'] }}</div>
                                            @foreach ($seance['matieres'] as $matiere)
                                                <div class="matiere">{{ $matiere }}</div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
