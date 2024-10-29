<?php
session_start(); // Inicia a sessão
require 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

function read_pdf($file_path) {
    $parser = new Parser();
    $pdf = $parser->parseFile($file_path);
    $text = $pdf->getText();

    // Limpeza e formatação do texto
    $text = preg_replace('/\s+/', ' ', $text); // Remove múltiplos espaços

    return $text;
}

function summarize_text($text, $max_words = 200) {
    $sentences = preg_split('/(?<=[.!?])\s+/', $text); // Divide o texto em frases
    $summary = "";
    $word_count = 0;

    foreach ($sentences as $sentence) {
        $sentence_word_count = str_word_count($sentence);
        if ($word_count + $sentence_word_count > $max_words) {
            break; // Se o limite de palavras for atingido, interrompa
        }
        $summary .= $sentence . ' '; // Adiciona a frase ao resumo
        $word_count += $sentence_word_count;
    }

    return trim($summary) . '...'; // Adiciona reticências para indicar que o texto foi resumido
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $file = $_FILES['pdf_file'];
    $file_path = $file['tmp_name'];

    // Obtendo o limite de palavras do formulário
    $max_words = isset($_POST['max_words']) ? (int)$_POST['max_words'] : 100;

    if ($file['error'] === UPLOAD_ERR_OK) {
        $text = read_pdf($file_path);
        $_SESSION['summary'] = summarize_text($text, $max_words); // Armazena o resumo na sessão
        $_SESSION['max_words'] = $max_words; // Armazena o limite de palavras na sessão
        $_SESSION['original_summary'] = $_SESSION['summary']; // Armazena o resumo original
    } else {
        $error = 'Erro ao fazer upload do arquivo.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leitor de PDF</title>
    <!-- Adicionando Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #ece9e6 0%, #ffffff 100%);
            font-family: 'Arial', sans-serif;
            color: #333;
        }
        .container {
            margin-top: 50px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.9);
        }
        h1 {
            font-size: 2.5rem;
            color: #007bff;
        }
        .btn-custom {
            background-color: #007bff;
            color: white;
            border: none;
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn-custom:hover {
            background-color: #0056b3;
            transform: scale(1.05);
        }
        .input-group {
            margin-bottom: 20px; /* Espaço entre o campo de input e os botões */
        }
        input[type="text"], input[type="number"] {
            height: 50px; /* Aumentando a altura do campo de entrada */
            border: 2px solid #007bff; /* Borda azul */
            border-radius: 5px; /* Bordas arredondadas */
        }
        input[type="text"]:focus, input[type="number"]:focus {
            border-color: #0056b3; /* Borda azul escura ao focar */
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }
        #summaryText {
            min-height: 150px; /* Altura mínima do resumo */
            overflow-y: auto; /* Adiciona rolagem se o conteúdo for muito longo */
            padding: 10px; /* Espaçamento interno */
            background-color: #ffffff; /* Fundo branco para o resumo */
            border: 2px solid #007bff; /* Borda azul */
            border-radius: 5px; /* Bordas arredondadas */
            transition: background-color 0.3s;
        }
        #summaryText:hover {
            background-color: #f0f8ff; /* Cor de fundo ao passar o mouse */
        }
        .highlight {
            background-color: yellow; /* Cor de destaque para o texto */
        }
        .no-results {
            color: red; /* Cor para mensagem de nenhum resultado */
            display: none; /* Ocultar por padrão */
        }
        .card {
            border: none;
            margin-top: 20px;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .card-body {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center">Leitor de PDF</h1>
        <form method="POST" enctype="multipart/form-data" class="text-center mb-4">
            <div class="form-group">
                <label for="pdf_file" class="font-weight-bold">Selecione um arquivo PDF:</label>
                <input type="file" name="pdf_file" id="pdf_file" accept=".pdf" class="form-control-file" required>
            </div>
            <div class="form-group">
                <label for="max_words" class="font-weight-bold">Número máximo de palavras no resumo:</label>
                <input type="number" name="max_words" id="max_words" class="form-control" min="1" max="500" value="<?php echo isset($_SESSION['max_words']) ? htmlspecialchars($_SESSION['max_words']) : 100; ?>" required>
            </div>
            <div class="input-group">
                <button type="submit" class="btn btn-custom">Processar <i class="fas fa-upload"></i></button>
                <input type="text" id="searchInput" class="form-control" placeholder="Buscar texto">
                <button type="button" class="btn btn-custom" id="filterButton">Filtrar <i class="fas fa-filter"></i></button>
            </div>
            <label id="noResults" class="no-results">Nenhuma pesquisa compatível.</label> <!-- Label para resultados não encontrados -->
        </form>

        <?php if (isset($_SESSION['summary'])): ?>
            <div class="alert alert-success" role="alert">
                Resumo gerado com sucesso!
            </div>
            <div class="card">
                <div class="card-header">
                    Resumo do PDF
                </div>
                <div class="card-body">
                    <h5 class="card-title">Resumo (até <?php echo htmlspecialchars($_SESSION['max_words']); ?> palavras)</h5>
                    <div id="summaryText"><?php echo nl2br(htmlspecialchars($_SESSION['summary'])); ?></div> <!-- Resumo em um div -->
                    <button id="copyButton" class="btn btn-secondary mt-2">Copiar Resumo</button>
                </div>
            </div>
            <script>
                document.getElementById('copyButton').onclick = function() {
                    const div = document.getElementById('summaryText');
                    const range = document.createRange();
                    range.selectNodeContents(div);
                    const sel = window.getSelection();
                    sel.removeAllRanges(); // Remove qualquer seleção anterior
                    sel.addRange(range);
                    document.execCommand('copy');
                    alert('Resumo copiado para a área de transferência!');
                };

                document.getElementById('filterButton').onclick = function() {
                    const searchTerm = document.getElementById('searchInput').value.trim();
                    const originalSummary = "<?php echo addslashes($_SESSION['original_summary']); ?>"; // Obtem o resumo original
                    const summaryText = document.getElementById('summaryText');
                    
                    // Restaura o conteúdo original do resumo
                    summaryText.innerHTML = originalSummary;

                    const highlightedText = summaryText.innerHTML.replace(new RegExp(`(${searchTerm})`, 'gi'), '<span class="highlight">$1</span>');

                    summaryText.innerHTML = highlightedText; // Atualiza o conteúdo do resumo com o texto destacado

                    // Verifica se houve correspondência
                    if (highlightedText.includes('<span class="highlight">')) {
                        document.getElementById('noResults').style.display = 'none'; // Oculta a mensagem de sem resultados
                    } else {
                        document.getElementById('noResults').style.display = 'block'; // Exibe a mensagem de sem resultados
                    }
                };
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
