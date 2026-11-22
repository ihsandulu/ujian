<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

class Api extends CI_Controller
{
    private $kode_menu = 'laporan-rekap';
    private $kelompok = 'laporan';
    private $url = 'api';

    function __construct()
    {
        parent::__construct();        
        $this->db2 = $this->load->database('database_kedua', TRUE);
    }

    public function rekap()
    {
        $grup = $this->input->get('grup');
        $awal = $this->input->get('awal');
        $akhir = $this->input->get('akhir');

        $sql = "
        SELECT
            u.user_id,
            u.user_firstname,
            u.user_grup_id,
            t.tes_id,
            t.tes_nama,
            t.tes_begin_time,
            SUM(s.tessoal_nilai) AS nilai
        FROM cbt_user u
        JOIN cbt_tesgrup tg
            ON tg.tstgrp_grup_id = u.user_grup_id
        JOIN cbt_tes t
            ON t.tes_id = tg.tstgrp_tes_id
        LEFT JOIN cbt_tes_user tu
            ON tu.tesuser_tes_id = t.tes_id
            AND tu.tesuser_user_id = u.user_id
        LEFT JOIN cbt_tes_soal s
            ON s.tessoal_tesuser_id = tu.tesuser_id
        WHERE u.user_grup_id = ?
            AND DATE(t.tes_begin_time) BETWEEN ? AND ?
        GROUP BY
            u.user_id,
            u.user_firstname,
            u.user_grup_id,
            t.tes_id,
            t.tes_nama,
            t.tes_begin_time,
            tu.tesuser_id
        ORDER BY t.tes_begin_time, t.tes_nama
    ";

        $data = $this->db->query(
            $sql,
            array($grup, $awal, $akhir)
        )->result_array();

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
