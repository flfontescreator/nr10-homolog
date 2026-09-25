<?php

namespace App\Http\Controllers;

use App\Enums\Source;
use App\Models\TenantItem;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;

class AjudaController extends Controller
{
    public function index(): View
    {
        $tenant = TenantContext::current();

        $defaults = [
            ['module' => 'Geral', 'key' => 'Login', 'value' => 'Email + senha forte (mín. 8, maiúscula, minúscula, número, símbolo). Botão "manter-me conectado" estende a sessão para 7 dias.'],
            ['module' => 'Geral', 'key' => 'Esqueci a senha', 'value' => 'Clique em "Esqueci a senha", informe o e-mail e redefina pelo link enviado (no piloto o link aparece em storage/logs/laravel.log).'],
            ['module' => 'Geral', 'key' => 'Seletor de cliente', 'value' => 'No menu superior, o super admin troca o ambiente pelo seletor. Cada cliente só vê os próprios dados.'],
            ['module' => 'Papéis', 'key' => 'Super Admin', 'value' => 'Acesso total em todos os níveis/clientes. Cria clientes e super admins.'],
            ['module' => 'Papéis', 'key' => 'Admin', 'value' => 'Gerencia os usuários do seu cliente e registros; exclui evidências.'],
            ['module' => 'Papéis', 'key' => 'Manager', 'value' => 'Altera registros (campos de controle) e anexa evidências, mas não exclui.'],
            ['module' => 'Papéis', 'key' => 'Viewer', 'value' => 'Somente leitura.'],
            ['module' => 'Cronograma', 'key' => 'Catálogo fixo', 'value' => 'Itens/subitens e detalhamento vêm da planilha "Cronograma de Adequação NR-10". Criticidade e setores são escolhidos por subitem (o valor padrão vem do catálogo, mas pode ser alterado).'],
            ['module' => 'Cronograma', 'key' => 'Campos de controle', 'value' => 'Data da inspeção, condição inicial, criticidade, setores, descrição da NC, ID-relatório, prazo de adequação, ação, ação realizada, data da realização, responsável e status são preenchidos por cliente.'],
            ['module' => 'Cronograma', 'key' => 'Evidências', 'value' => 'Cada subitem aceita N evidências (fotos, PDF, docs). Executáveis (.exe, .php, .bat etc.) são rejeitados.'],
            ['module' => 'Prontuário', 'key' => 'Média Geral', 'value' => 'Calculada automaticamente pela média dos percentuais dos subitens de cada seção.'],
            ['module' => 'Prontuário', 'key' => 'Status de evidências', 'value' => 'Valores fixos: Digital, Pendente, Nao Aplicado.'],
            ['module' => 'Gestão de Documentos', 'key' => 'Arquivos', 'value' => 'Lista central de todas as evidências do cliente, com download por quem tem acesso de leitura.'],
            ['module' => 'Erros', 'key' => 'Sem tela em branco', 'value' => 'Erros exibem mensagem com caminho de resolução. Cadastros dependentes bloqueados mostram o motivo.'],
        ];

        $counts = [];
        if ($tenant) {
            $counts['cronograma'] = TenantItem::whereHas('catalogItem', fn ($q) => $q->where('source', Source::Cronograma->value))->count();
            $counts['prontuario'] = TenantItem::whereHas('catalogItem', fn ($q) => $q->where('source', Source::Prontuario->value))->count();
        }

        return view('ajuda.index', [
            'defaults' => collect($defaults),
            'tenant' => $tenant,
            'counts' => $counts,
        ]);
    }
}
