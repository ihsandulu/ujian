<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

class Peserta_import extends Member_Controller
{
    private $kode_menu = 'peserta-import';
    private $kelompok = 'peserta';
    private $url = 'manager/peserta_import';

    function __construct()
    {
        parent::__construct();
        $this->load->model('cbt_user_grup_model');
        $this->load->model('cbt_user_model');

        parent::cek_akses($this->kode_menu);
    }

    public function index()
    {
        $this->import();
    }

    public function import()
    {
        $data['kode_menu'] = $this->kode_menu;
        $data['url'] = $this->url;

        $this->load->library('form_validation');

        $data['error'] = '';
        $data['error_upload'] = '';

        if (!empty($_FILES['userfile']['name'])) {
            $config['upload_path'] = './public/uploads/';
            $config['allowed_types'] = 'xls|xlsx';
            $config['max_size']    = '0';
            $config['overwrite'] = true;
            $config['file_name'] = $_FILES['userfile']['name'];

            $this->load->library('upload', $config);
            if (!$this->upload->do_upload()) {
                $data['error_upload'] = $this->upload->display_errors() . 'Tipe file yang di upload adalah ' . $_FILES['userfile']['type'];
            } else {
                $upload_data = $this->upload->data();
                $data['filename'] = 'File ' . $upload_data['file_name'] . ' BERHASIL di IMPORT';

                // disini proses import data
                $data['hasil'] = $this->import_file($upload_data['file_name']);
            }
        } else {
            $data['error_upload'] = 'Pilih File yang akan di IMPORT';
        }

        $this->template->display_admin($this->kelompok . '/peserta_import_view', 'Import Peserta', $data);
    }


    function import_file($inputfile)
    {
        $this->load->library('excel');
        $inputFileName = './public/uploads/' . $inputfile;

        $excel = PHPExcel_IOFactory::load($inputFileName);
        $worksheet = $excel->getSheet(0);
        $highestRow = $worksheet->getHighestRow();

        $db2 = $this->load->database('database_kedua', TRUE);

        $pesan = '<div class="alert alert-info alert-dismissable">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-info"></i> Informasi!</h4>';

        $jmldatasukses = 0;
        $jmldataerror = 0;
        $jumlahbaris = 0;

        if ($highestRow >= 2) {

            for ($row = 2; $row <= $highestRow; $row++) {

                // Urutan Excel: NISN, Password, Email, Keterangan
                $nisn = trim((string) $worksheet->getCellByColumnAndRow(0, $row)->getFormattedValue());
                $password = trim((string) $worksheet->getCellByColumnAndRow(1, $row)->getValue());
                $email = trim((string) $worksheet->getCellByColumnAndRow(2, $row)->getValue());
                $detail = trim((string) $worksheet->getCellByColumnAndRow(3, $row)->getValue());

                // Lewati baris yang seluruh kolomnya kosong
                if ($nisn == '' && $password == '' && $email == '' && $detail == '') {
                    continue;
                }

                $jumlahbaris++;

                if ($nisn == '' || $password == '') {
                    $pesan .= 'Baris ' . $row . ': NISN dan Password wajib diisi.<br>';
                    $jmldataerror++;
                    continue;
                }

                // Periksa NISN sudah terdaftar di database pertama
                $cek_nisn = $this->cbt_user_model
                    ->count_by_kolom('user_nisn', $nisn)->row()->hasil;

                $cek_username = $this->cbt_user_model
                    ->count_by_kolom('user_name', $nisn)->row()->hasil;

                if ($cek_nisn > 0 || $cek_username > 0) {
                    $pesan .= 'NISN ' . htmlspecialchars($nisn) . ' sudah digunakan.<br>';
                    $jmldataerror++;
                    continue;
                }

                // Cari data peserta di database kedua
                $user = $db2
                    ->where('user_nisn', $nisn)
                    ->where('sekolah_id', 1)
                    ->get('user')
                    ->row();

                if (!$user) {
                    $pesan .= 'NISN ' . htmlspecialchars($nisn) . ' tidak ditemukan di database sumber.<br>';
                    $jmldataerror++;
                    continue;
                }

                // Cocokkan kelas_id dengan kelas_id pada group CBT
                $grup = $this->db
                    ->where('kelas_id', $user->kelas_id)
                    ->get('cbt_user_grup')
                    ->row();

                if (!$grup) {
                    $pesan .= 'Group untuk kelas_id ' . htmlspecialchars($user->kelas_id) . ' belum dibuat.<br>';
                    $jmldataerror++;
                    continue;
                }

                // Simpan peserta
                $data = array(
                    'user_name'        => $user->user_nisn,
                    'user_password'    => $password,
                    'user_email'       => $email,
                    'user_firstname'   => $user->user_name,
                    'user_grup_id'     => $grup->grup_id,
                    'user_detail'      => $detail,
                    'user_nisn'        => $user->user_nisn,
                    'user_tahunajaran' => $user->user_tahunajaran
                );

                $this->cbt_user_model->save($data);
                $jmldatasukses++;
            }

            $pesan .= '<br>
            Jumlah data yang berhasil diimport adalah ' . $jmldatasukses . '<br>
            Jumlah data yang gagal diimport adalah ' . $jmldataerror . '<br>
            Jumlah total baris yang diproses adalah ' . $jumlahbaris . '<br>';
        } else {
            $pesan .= 'Tidak ada data yang dapat diimport. Periksa kembali file Excel.';
        }

        $pesan .= '</div>';

        return $pesan;
    }
}
