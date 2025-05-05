import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.IOException;
import java.io.RandomAccessFile;
import java.io.UnsupportedEncodingException;
import java.nio.ByteBuffer;
import java.nio.channels.FileChannel;
 
/**
 * Read a file from end to start
 * 
 * @author Crunchify.com
 */
 
public class CrunchifyReverseLineReaderCore {
	private static final int BUFFER_SIZE = 8192;
	private final FileChannel channel;
	private final String encoding;
	private long filePos;
	private ByteBuffer buf;
	private int bufPos;
	private ByteArrayOutputStream baos = new ByteArrayOutputStream();
	private RandomAccessFile raf;
 
	public ReverseLineReader(File file) throws IOException{
		this(file, null);
	}
	
	public ReverseLineReader(File file, String encoding)
			throws IOException {
		raf = new RandomAccessFile(file, "r");
		channel = raf.getChannel();
		filePos = raf.length();
		this.encoding = encoding;
	}
	
	public void close() throws IOException{
		raf.close();
	}
 
	public String readLine() throws IOException {
		byte c;
		while (true) {
			if (bufPos < 0) {
				if (filePos == 0) {
					if (baos == null) {
						return null;
					}
					String line = bufToString();
					baos = null;
					return line;
				}
 
				long start = Math.max(filePos - BUFFER_SIZE, 0);
				long end = filePos;
				long len = end - start;
 
				buf = channel.map(FileChannel.MapMode.READ_ONLY, start, len);
				bufPos = (int) len;
				filePos = start;
				
				//strip last newlines
				c = buf.get(--bufPos);
				if(c == '\r' || c == '\n')
					while(bufPos > 0 && (c == '\r' || c == '\n')){
						bufPos--;
						c = buf.get(bufPos);
					}
				if(!(c == '\r' || c == '\n'))
					bufPos++;//IS THE NEW LEN
			}
 
			while (bufPos-- > 0) {
				c = buf.get(bufPos);
				if (c == '\r' || c == '\n') {
					//skip \r\n
					while(bufPos > 0 && (c == '\r' || c == '\n')){
						c = buf.get(--bufPos);
					}
					//restore cursor
					if(!(c == '\r' || c == '\n'))
						bufPos++;//IS THE NEW LEN
					return bufToString();
				}
				baos.write(c);
			}
		}
	}
 
	private String bufToString() throws UnsupportedEncodingException {
		if (baos.size() == 0) {
			return "";
		}
 
		byte[] bytes = baos.toByteArray();
		for (int i = 0; i < bytes.length / 2; i++) {
			byte t = bytes[i];
			bytes[i] = bytes[bytes.length - i - 1];
			bytes[bytes.length - i - 1] = t;
		}
 
		baos.reset();
		if(encoding != null)
			return new String(bytes, encoding);
		else
			return new String(bytes);
	}
	
	/**
	 * Return the last n lines of a file
	 * @param fpath file to read
	 * @param numberOfLines a positive number of lines to read
	 * @param encoding null or the string encoding
	 * @return loaded lines \n separated
	 * @throws IOException
	 */
	public static StringBuilder getLastLines(String fpath, int numberOfLines, String encoding) 
			throws IOException{
		if(fpath == null || numberOfLines <= 0)
			throw new IllegalArgumentException();
		
		File f = new File(fpath);
		if(!(f.exists() && f.isFile() && f.canRead()))
			throw new IllegalArgumentException();
		
		String line;
		StringBuilder sb = new StringBuilder();
		ReverseLineReader reader = null;
		try {
			reader = new ReverseLineReader(f, encoding);
			int n = numberOfLines;
			while((line=reader.readLine()) != null && n >= 0){
				sb.insert(0, line + "\n");
				n--;
			}
		} catch (IOException e) {
			throw e;
		} finally{
			if(reader != null){
				try{
					reader.close();
				} catch(Exception e){
					e.printStackTrace();
				}
			}
		}
		return sb;
	}
}
