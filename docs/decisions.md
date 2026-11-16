# Decisões

O porquê de cada escolha q não é óbvia lendo o código.

## DOM nativo do PHP 8.4, não Symfony DomCrawler

O `Dom\HTMLDocument` do 8.4 tem parser HTML5 de verdade e `querySelector`. Isso tira a única dependência pesada q a lib teria e mostra PHP atual. O custo: ainda é novo. O seletor `:scope` e a propriedade `children` não existem nele, então os links diretos de um título são lidos pelos irmãos (`firstElementChild`/`nextElementSibling`).

## Payloads lidos antes de qualquer limpeza

JSON-LD, `__NEXT_DATA__` e `Fusion.globalContent` moram em `<script>`, e limpar o DOM apaga script. Por isso `Page::parse` decodifica tudo logo depois do parse, e nenhuma camada altera o documento da página: quem limpa trabalha num clone. Um teste roda as seis camadas e confere q o HTML da página não mudou.

## Piso de 300 caracteres de corpo

Sem piso, um `<article>` com um parágrafo de chamada ganha do JSON-LD q tem a matéria inteira. 300 caracteres é mais ou menos 50 palavras: acima de quase todo teaser e abaixo de quase toda nota curta. Título de seção e legenda não contam pro piso.

## Ruído por nome inteiro ou prefixo, não por pedaço

Casar "sidebar" em qualquer lugar da classe derruba o wrapper `has-sidebar`, q costuma ter o corpo dentro. A regra é: a classe ou o id é igual ao padrão ou começa com ele seguido de `-` ou `_`. `<form>` não é ruído por si: tem portal q embrulha a página inteira num form.

## Chamada só derruba bloco curto

"Leia também" num bloco de 40 caracteres é chamada; "publicidade" num parágrafo de 600 caracteres sobre o mercado de publicidade é matéria. As frases de chamada só valem abaixo de 200 caracteres. Carimbo de data ("Publicado em 05/03/2026 10:30") é linha de até 80 caracteres q começa com data e tem até 6 palavras: "05/03/2026 foi o dia em q o país mudou" fica.

## Densidade: pai e metade pro avô, somados numa passada

Somar o texto de todos os descendentes faz o wrapper mais de fora sempre vencer. Cada parágrafo dá o seu tamanho ao pai e metade ao avô: parágrafos irmãos se somam no container certo, parágrafos embrulhados um a um se somam no avô. Container com mais de 25% do texto em link é pulado.

Tamanho do texto e do texto em link de cada elemento são somados de baixo pra cima numa passada só. A primeira versão media cada candidato descendo pela subárvore dele, o q fica quadrático numa página com milhares de `<div>` sem fechar (comum em template quebrado). Pelo mesmo motivo, a extração de blocos percorre o container uma vez carregando o contexto (dentro de link, item, citação, legenda) em vez de perguntar `closest()` pra cada nó.

## HTML reconstruído, não limpo

Limpar o HTML original exigiria lista de tags e atributos permitidos, filtro de esquema de URL e ainda não resolveria corpo vindo de JSON-LD ou de estado de app. Remontar a partir dos blocos, escapando todo texto, é seguro por construção. O custo: o corpo sai sem links e sem imagens.

## Sem HTTP na lib

Baixar página é onde mora retry, timeout, rate limit, robots.txt e SSRF, e cada aplicação resolve isso do seu jeito. A lib recebe string e devolve objeto: testável sem rede e sem dependência. O `robots.txt` é só parseado aqui (RFC 9309: vale o grupo com o nome exato do produto, sem caixa e sem versão, senão o `*`; grupos repetidos valem juntos; BOM no começo não muda nada); quem respeita é quem baixa.

## Datas: só formato conhecido, sempre UTC

`strtotime` aceita "ontem" e transforma 31/02 em 3 de março. Aqui só entra ISO-8601, RFC 2822 e dd/mm/aaaa com hora opcional; data impossível vira `null`. Data sem fuso é lida no fuso q quem chama informar (padrão UTC), e o resultado sai sempre em UTC.

## Repetição do título sem distância de edição

O título ou a linha fina repetidos como primeiro parágrafo saem do corpo. Repetir aqui é ter o mesmo texto depois de tirar caixa e pontuação, ou um texto conter o outro com tamanhos quase iguais (mais de 90%). A primeira versão usava distância de edição, q é quadrática: uma linha fina de 10 mil caracteres travava a extração por um minuto. A comparação atual é linear. Descrição com mais de 500 caracteres não é linha fina, é o próprio corpo, e nunca apaga parágrafo.

## JSON e feed quebrados são consertados, não descartados

Template feito à mão põe quebra de linha crua dentro de string JSON, embrulha o JSON-LD em CDATA ou comentário, e feed real tem `&` solto, `&nbsp;` e byte inválido. Recusar o documento inteiro por isso joga fora título, autor, data e corpo. Por isso o parse estrito vem primeiro e, só se falhar, uma segunda tentativa conserta o q dá: escapa caractere de controle dentro de string, tira o embrulho, troca entidade HTML por referência numérica e escapa `&` solto. Feed q declara outra codificação não tem os bytes mexidos.

## Listas genéricas

Seletores de corpo, padrões de ruído e frases de chamada são convenções públicas (schema.org, WordPress, Arc XP, Next.js) em português e inglês. Nenhuma entrada é de um site só: lista por site é exatamente o q a lib existe pra evitar.

## Mutação em 97%

O CI exige 97% no `pest --mutate`. Os mutantes q sobram são equivalentes, ou seja, mudam o código sem mudar nenhuma saída possível:

- `?? ''` em `textContent` de elemento e em atributo opcional, q nunca é `null` ali;
- casts `(string)` em `preg_replace` sobre UTF-8 válido, q nunca devolve `null`;
- `?? 0` e `+ 1`/`- 1` nos acumuladores de ponto da densidade, q deslocam todos os candidatos igual;
- limites de lista de chamadas com 3 parágrafos, q nunca passam do piso de 300 caracteres;
- multiplicadores aplicados igual a todos os candidatos, q não mudam a ordem;
- limites de 0x20 no conserto de JSON (escapar o espaço dá o mesmo texto).
