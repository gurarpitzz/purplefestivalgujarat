import { PDFDocument, rgb, StandardFonts } from "pdf-lib";
import QRCode from "qrcode";
import { FESTIVAL_DATES_DISPLAY, FESTIVAL_LOCATION_DISPLAY, FESTIVAL_NAME } from "./constants";

export async function generateAccessiblePassPdf(params: {
  participantName: string;
  registrationCode: string;
  credentialCode: string;
  categoryName: string;
  status: string;
  qrPayload: string;
  roleAssignment?: string | null;
}): Promise<Uint8Array> {
  const pdfDoc = await PDFDocument.create();
  pdfDoc.setTitle(`${FESTIVAL_NAME} — Digital Entry Pass (${params.registrationCode})`);
  pdfDoc.setAuthor(FESTIVAL_NAME);
  pdfDoc.setSubject(`Accessible Digital Event Pass for ${params.participantName}`);

  const page = pdfDoc.addPage([420, 620]);
  const fontBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
  const fontRegular = await pdfDoc.embedFont(StandardFonts.Helvetica);

  // Charcoal Black background (#17151C)
  page.drawRectangle({
    x: 0,
    y: 0,
    width: 420,
    height: 620,
    color: rgb(0.09, 0.082, 0.11),
  });

  // Top Dusty Purple accent bar (#9273B5)
  page.drawRectangle({
    x: 0,
    y: 530,
    width: 420,
    height: 90,
    color: rgb(0.349, 0.263, 0.427),
  });

  // Gold thin accent rule (#D5B77B)
  page.drawRectangle({
    x: 0,
    y: 526,
    width: 420,
    height: 4,
    color: rgb(0.835, 0.718, 0.482),
  });

  // Header text
  page.drawText("PURPLE FESTIVAL GUJARAT 2027", {
    x: 28,
    y: 582,
    size: 16,
    font: fontBold,
    color: rgb(0.976, 0.969, 0.988),
  });
  page.drawText("Celebrating Accessibility, Inclusion, Dignity & Diversity", {
    x: 28,
    y: 560,
    size: 9.5,
    font: fontRegular,
    color: rgb(0.91, 0.875, 0.941),
  });
  page.drawText(`${FESTIVAL_DATES_DISPLAY}  |  ${FESTIVAL_LOCATION_DISPLAY}`, {
    x: 28,
    y: 542,
    size: 9.5,
    font: fontBold,
    color: rgb(0.835, 0.718, 0.482),
  });

  // Participant details card
  page.drawRectangle({
    x: 24,
    y: 350,
    width: 372,
    height: 156,
    color: rgb(0.145, 0.129, 0.169),
    borderColor: rgb(0.573, 0.451, 0.71),
    borderWidth: 1,
  });

  page.drawText("PARTICIPANT NAME", {
    x: 40,
    y: 484,
    size: 8.5,
    font: fontBold,
    color: rgb(0.714, 0.631, 0.784),
  });
  page.drawText(params.participantName.slice(0, 38), {
    x: 40,
    y: 462,
    size: 16,
    font: fontBold,
    color: rgb(0.976, 0.969, 0.988),
  });

  page.drawText("REGISTRATION CATEGORY", {
    x: 40,
    y: 432,
    size: 8.5,
    font: fontBold,
    color: rgb(0.714, 0.631, 0.784),
  });
  page.drawText(params.categoryName.toUpperCase(), {
    x: 40,
    y: 414,
    size: 12,
    font: fontBold,
    color: rgb(0.835, 0.718, 0.482),
  });

  page.drawText("CREDENTIAL ID", {
    x: 40,
    y: 386,
    size: 8.5,
    font: fontBold,
    color: rgb(0.714, 0.631, 0.784),
  });
  page.drawText(params.credentialCode, {
    x: 40,
    y: 368,
    size: 12,
    font: fontBold,
    color: rgb(0.976, 0.969, 0.988),
  });

  page.drawText("STATUS", {
    x: 260,
    y: 386,
    size: 8.5,
    font: fontBold,
    color: rgb(0.714, 0.631, 0.784),
  });
  page.drawText(params.status.toUpperCase(), {
    x: 260,
    y: 368,
    size: 12,
    font: fontBold,
    color: rgb(0.32, 0.85, 0.58),
  });

  // QR Code Image
  const qrBuffer = await QRCode.toBuffer(params.qrPayload, {
    type: "png",
    width: 240,
    margin: 2,
    color: { dark: "#17151C", light: "#F9F7FC" },
  });
  const qrImage = await pdfDoc.embedPng(qrBuffer);

  page.drawRectangle({
    x: 120,
    y: 132,
    width: 180,
    height: 180,
    color: rgb(0.976, 0.969, 0.988),
  });
  page.drawImage(qrImage, {
    x: 128,
    y: 140,
    width: 164,
    height: 164,
  });

  // Accessible text alternative & instructions
  if (params.roleAssignment) {
    page.drawText(`Assigned Role: ${params.roleAssignment.slice(0, 56)}`, {
      x: 28,
      y: 102,
      size: 9.5,
      font: fontBold,
      color: rgb(0.91, 0.875, 0.941),
    });
  }

  page.drawText(
    "Present this QR pass or quote your Credential ID at the Registration Desk (RGST).",
    {
      x: 28,
      y: 74,
      size: 8.5,
      font: fontRegular,
      color: rgb(0.714, 0.631, 0.784),
    }
  );
  page.drawText(
    "No personal medical or disability information is embedded in this QR code.",
    {
      x: 28,
      y: 56,
      size: 8.5,
      font: fontRegular,
      color: rgb(0.714, 0.631, 0.784),
    }
  );
  page.drawText("Official Digital Credential — www.purplefestivalgujarat.com", {
    x: 28,
    y: 28,
    size: 8,
    font: fontRegular,
    color: rgb(0.573, 0.451, 0.71),
  });

  return pdfDoc.save();
}

