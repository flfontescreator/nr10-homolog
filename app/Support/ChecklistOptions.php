<?php

namespace App\Support;

/**
 * Lista fixa de setores da página Não Conformidades (check-list de
 * instalações elétricas), definida pelo cliente.
 */
class ChecklistOptions
{
    public static function setores(): array
    {
        return [
            'Engenharia',
            'Engenharia Elétrica',
            'Empresa Especializada',
            'Facilities',
            'Manutenção',
            'Manutenção Elétrica',
            'Manutenção Predial',
            'Mecânica',
            'Meio Ambiente',
            'QSMS',
            'Responsáveis pela Emissão de PT',
            'Serviços Gerais',
            'Segurança do Trabalho',
            'Supervisão da Manutenção',
            'Supervisão Operacional',
        ];
    }
}
