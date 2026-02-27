<!DOCTYPE html>
<html>

<head>
    <title>Claesen Verlichting - Speciale Aanbieding voor {{ $prospect->name }}</title>
</head>

<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="text-align: center; margin-bottom: 20px;">
        <h2>Beste verantwoordelijke van {{ $prospect->name }},</h2>
    </div>

    <p>Wij zijn Claesen Verlichting, specialisten in professionele sportverlichting.</p>

    <p>Als club in de regio <strong>{{ $prospect->region ?? 'Vlaanderen' }}</strong>, begrijpen we hoe belangrijk kwalitatieve verlichting is voor jullie spelers en supporters.</p>

    <p>Heeft u interesse in een vrijblijvende audit van uw huidige lichtinstallatie?</p>

    <br>

    <p>Met sportieve groeten,</p>
    <p><strong>Het Claesen Team</strong><br>
        <a href="https://www.claesen.be">www.claesen.be</a>
    </p>

</body>

</html>