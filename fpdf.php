<?php
/*******************************************************************************
* FPDF 1.82 (versión reducida y adaptada)
* Fuente oficial: http://www.fpdf.org
*******************************************************************************/
class FPDF
{
    protected $page; 
    protected $n; 
    protected $offsets; 
    protected $buffer; 
    protected $pages; 
    protected $state; 
    protected $compress; 
    protected $DefOrientation;
    protected $CurOrientation;
    protected $StdPageSizes; 
    protected $DefPageSize; 
    protected $CurPageSize;
    protected $k; 
    protected $fwPt;
    protected $fhPt;
    protected $fw;
    protected $fh;
    protected $wPt;
    protected $hPt;
    protected $w;
    protected $h;
    protected $lMargin;
    protected $tMargin;
    protected $rMargin;
    protected $bMargin;
    protected $cMargin;
    protected $x;
    protected $y;
    protected $LineWidth;
    protected $fontpath;
    protected $fonts;
    protected $FontFiles;
    protected $diffs;
    protected $FontFamily;
    protected $FontStyle;
    protected $underline;
    protected $CurrentFont;
    protected $FontSizePt;
    protected $FontSize;
    protected $DrawColor;
    protected $FillColor;
    protected $TextColor;
    protected $ColorFlag;
    protected $ws;
    protected $images;
    protected $PageLinks;
    protected $links;
    protected $AutoPageBreak;
    protected $PageBreakTrigger;
    protected $InHeader;
    protected $InFooter;
    protected $lasth;
    protected $FontAscent;
    protected $FontDescent;

    function __construct($orientation='P', $unit='mm', $size='A4')
    {
        $this->page = 0;
        $this->n = 2;
        $this->buffer='';
        $this->pages=array();
        $this->PageLinks=array();
        $this->links=array();
        $this->fonts=array();
        $this->FontFiles=array();
        $this->diffs=array();
        $this->images=array();
        $this->InHeader=false;
        $this->InFooter=false;
        $this->lasth=0;
        $this->FontFamily='';
        $this->FontStyle='';
        $this->FontSizePt=12;
        $this->underline=false;
        $this->DrawColor='0 G';
        $this->FillColor='0 g';
        $this->TextColor='0 g';
        $this->ColorFlag=false;
        $this->ws=0;

        if($unit=='mm'){
            $this->k=72/25.4;
        } else {
            $this->k=1;
        }

        $this->DefOrientation=strtoupper($orientation);
        $this->CurOrientation=$this->DefOrientation;

        $this->StdPageSizes=array(
            'A4'=>array(595.28,841.89)
        );

        $this->DefPageSize=$this->StdPageSizes['A4'];
        $this->CurPageSize=$this->DefPageSize;

        $this->w=$this->CurPageSize[0]/$this->k;
        $this->h=$this->CurPageSize[1]/$this->k;

        $this->lMargin=10;
        $this->tMargin=10;
        $this->rMargin=10;
        $this->bMargin=10;
        $this->cMargin=2;

        $this->x=$this->lMargin;
        $this->y=$this->tMargin;

        $this->LineWidth=0.2;

        $this->SetFont('Arial','',12);
    }

    function SetFont($family, $style='', $size=12)
    {
        $this->FontFamily=$family;
        $this->FontStyle=$style;
        $this->FontSizePt=$size;
        $this->FontSize=$size/$this->k;
    }

    function AddPage()
    {
        $this->page++;
        $this->pages[$this->page]='';
        $this->x=$this->lMargin;
        $this->y=$this->tMargin;
    }

    function SetXY($x, $y)
    {
        $this->x=$x;
        $this->y=$y;
    }

    function SetX($x)
    {
        $this->x=$x;
    }

    function SetY($y)
    {
        $this->y=$y;
    }

    function Cell($w, $h, $txt='', $border=0, $ln=0)
    {
        $s=sprintf('BT %.2F %.2F Td (%s) Tj ET', $this->x*$this->k, ($this->h-$this->y)*$this->k, $this->_escape($txt));
        $this->_out($s);

        if($ln>0)
            $this->y+=$h;

        if($border)
            $this->_out(sprintf('%.2F %.2F %.2F %.2F re S',$this->x*$this->k,($this->h-$this->y)*$this->k,$w*$this->k,$h*$this->k));
    }

    function Ln($h=null)
    {
        $this->x=$this->lMargin;
        if($h===null)
            $this->y+=$this->FontSize;
        else
            $this->y+=$h;
    }

    function Output()
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="compras.pdf"');

        echo "%PDF-1.3\n";
        echo "1 0 obj <<>> endobj\n";
        echo "2 0 obj << /Length ".strlen($this->buffer)." >> stream\n";
        echo $this->buffer."\nendstream\nendobj\n";
        echo "xref\n0 3\n0000000000 65535 f \n";
        echo "%%EOF";
    }

    protected function _out($s)
    {
        $this->buffer.=$s."\n";
    }

    protected function _escape($s)
    {
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s);
    }
}
?>
